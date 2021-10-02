<?php
/**
 * OrderPage Class
 * 
 * Questo file contiene la classe OrderPage che serve a gestire la pagina degli ordini
 * @author Mattia <info@matriz.it>
 * @package MatCMS\Gastmo
 * @link http://www.matriz.it/projects/matcms/ MatCMS
 * @link http://www.matriz.it/projects/gastmo/ Gastmo
 */

namespace Gastmo;

if (!defined('_INCLUDED')) {
	die('Access denied!');
}

/**
 * La classe OrderPage contiene tutti i metodi per gestire la pagina degli ordini
 */
class OrderPage extends \ModulePage {
	/**
	 * Stampa la pagina in base ai parametri
	 * @access public
	 * @return array
	 */
	public function _printPage() {
		global $page;
		if (isset($this->params_url[1]) && $this->params_url[1] == 'save') {
			$this->saveCart();
		} else if (isset($this->params_url[1]) && $this->params_url[1] == 'getorderedproducts') {
			$this->getOrderedProducts();
		} else {
			$head_include = array('<script type="text/javascript"> order.base_path = \''._BASEPATH.'\'; </script>');
			define('_ISORDER', true);
			$breadcrumb = array(array('url' => '/order/', 'title' => 'Ordini'));
			$logged = $page->checkLogin();
			list($oid, $shippable) = $this->getShippableOrder(true);
			$order = new Order($oid);
			if ($order->exists()) {
				$id_user = \User::getLoggedUser();
				$is_closed = in_array($order->get('status'), array(Order::STATUS_DELIVERING, Order::STATUS_DELIVERED));
				$has_actual_qty = false;
				if ($is_closed) {
					if (isset($_POST['product_vote'])) {
						$this->saveVotes($order);
					}
					$p = new Product();
					$products = $p->getList(array(
						'select' => array(
							array('value' => 'products.*', 'no_quote' => true),
							'carts.qty',
							'carts.actual_qty',
							'carts.waste',
							'carts.vote',
							'carts.vote_descr'
						),
						'join' => array(
							'carts' => array('carts.product', 'products.id'),
						),
						'where' => array(
							array('field' => 'carts.order', 'value' => $order->get('id')),
							array('field' => 'carts.user', 'value' => $id_user)
						)
					));
					unset($p);
					if ($order->get('status') == Order::STATUS_DELIVERING) {
						$oe = new OrderExporter($order->get('id'));
						$json = @json_decode($oe->getCurrentExport('json'), true);
						unset($oe);
						$export_json_qty = is_array($json) && isset($json['qty']) && is_array($json['qty']) && isset($json['qty'][$id_user]) && is_array($json['qty'][$id_user]) ? $json['qty'][$id_user] : array();
						unset($json);
					} else {
						$export_json_qty = array();
					}
					$price_total = 0;
					$counter = count($products);
					for ($i = 0; $i < $counter; $i++) {
						if (!is_null($products[$i]['actual_qty'])) {
							$has_actual_qty = true;
						}
						if ($order->get('status') == Order::STATUS_DELIVERING) {
							$products[$i]['export_qty'] = isset($export_json_qty[$products[$i]['id']]) && is_numeric($export_json_qty[$products[$i]['id']])
								? floatval($export_json_qty[$products[$i]['id']])
								: 0;
						}
						$products[$i]['price_total'] = $products[$i]['price'] * $products[$i]['actual_qty'];
						$price_total += $products[$i]['price_total'];
					}
					unset($i, $counter, $export_json_qty);
					$extras = \DB::queryRows(\DB::createSql(array(
						'table' => 'carts_extras',
						'where' => array(
							array('field' => 'order', 'value' => $order->get('id')),
							array('field' => 'user', 'value' => $id_user)
						),
						'order' => array(
							'extra' => 'ASC'
						)
					)));
					if (is_array($extras)) {
						$counter = count($extras);
						for ($i = 0; $i < $counter; $i++) {
							if ($extras[$i]['extra'] != 'XV' || $extras[$i]['qty'] != 0) {
								$arr = array(
									'title' => $extras[$i]['title'],
									'um' => $extras[$i]['um'],
									'qty' => $extras[$i]['qty'],
									'actual_qty' => $extras[$i]['qty'],
									'price' => $extras[$i]['price'],
									'price_total' => $extras[$i]['price'] * $extras[$i]['qty']
								);
								$products[] = $arr;
								$price_total += $arr['price_total'];
							}
							unset($arr);
						}
						unset($i, $counter);
					}
					unset($extras);
				} else {
					/*if ($is_closed) {
						$orderObj = new OrderExporter($order->get('id'));
						$orderObj->downloadCurrentExport('html');
						unset($orderObj);
					}*/
					$pt = new ProductType();
					$arr = $pt->getList();
					unset($pt);
					$types = array();
					$counter = count($arr);
					for ($i = 0; $i < $counter; $i++) {
						$types[$arr[$i]['id']] = $arr[$i];
					}
					unset($i, $counter, $arr);
					$price_total = 0;
					$c = new Category();
					$categories = $c->getList(array(
						'where' => array(array('field' => 'order', 'value' => $order->get('id'))),
						'order' => array('pos' => 'ASC')
					));
					unset($c);
					$prepared_products = 'order_products_category';
					$p = new Product();
					\DB::prepare($prepared_products, $p->getSql(array(
						'where' => array(array('field' => 'category', 'value' => '?', 'value_type' => 'sql')),
						'order' => array('pos' => 'ASC')
					)));
					unset($p);
					$counter = count($categories);
					for ($i = 0; $i < $counter; $i++) {
						$products = \DB::queryRows(\DB::execPrepared($prepared_products, array($categories[$i]['id'])));
						$counter2 = count($products);
						for ($j = 0; $j < $counter2; $j++) {
							if (isset($types[$products[$j]['type']])) {
								$products[$j]['product_type'] = $types[$products[$j]['type']]['title'];
								$products[$j]['color'] = $types[$products[$j]['type']]['color'];
							} else {
								$products[$j]['product_type'] = '';
								$products[$j]['color'] = '';
							}
							$products[$j]['ordered'] = Product::getOrdered($products[$j]['id']);
							$products[$j]['user_ordered'] = $logged ? Product::getOrdered($products[$j]['id'], $id_user) : 0;
							$products[$j]['price_total'] = $products[$j]['user_ordered'] * $products[$j]['price'];
							if ($products[$j]['qty_package'] <= 0) {
								$products[$j]['qty_package'] = 1;
							}
							$ordered = $products[$j]['ordered'];
							while ($ordered > 0) {
								$ordered -= $products[$j]['qty_package'];
							}
							$products[$j]['completed'] = floor($products[$j]['ordered'] / $products[$j]['qty_package']);
							$products[$j]['to_complete'] = abs($ordered);
							unset($ordered);
							$price_total += $products[$j]['price_total'];
						}
						unset($j, $counter2);
						$categories[$i]['products'] = $products;
						unset($products);
					}
					unset($i, $counter, $prepared_products);
				}
				$title = $order->get('title');
				$url = Order::getURL($order);
				$breadcrumb[] = array('url' => $url, 'title' => $title);
				setBreadCrumb($breadcrumb);
				unset($breadcrumb);
				$u = new \User($order->get('admin'));
				$admin = $u->exists() ? $u->getData() : array();
				unset($u);
				$content = $this->printTemplate('order.php', array(
					'order' => array_merge($order->getData(), array(
						'price_total' => $price_total,
						'order_total' => $order->getUserTotal($id_user),
						'url' => $url,
						'is_closed' => $is_closed,
						'has_actual_qty' => $has_actual_qty
					)),
					'admin' => $admin,
					'c' => isset($categories) ? $categories : null,
					'products' => isset($products) ? $products : null,
					'logged' => $logged,
					'shippable' => $shippable,
					'was_shippable' => $shippable || Order::isShippable($order->get('id'), 0, true)
				));
				unset($has_actual_qty, $is_closed, $id_user);
				$new_content = \Hook::run('gastmo_print_order_html_content', array($content, $order), true);
				if (is_string($new_content)) {
					$content = $new_content;
				}
				unset($new_content);
				return array(
					'title' => 'Ordine: '.$title,
					'content' => $content,
					'url' => $url,
					'head_include' => $head_include
				);
			} else {
				$url = '/order/';
				if (isset($this->params_url[1]) && $this->params_url[1] == 'delivered') {
					$is_delivered = true;
					$title = 'Ordini consegnati';
					$url .= 'delivered/';
					$breadcrumb[] = array('url' => $url, 'title' => $title);
				} else {
					$is_delivered = false;
					$title = 'Ordini';
				}
				setBreadCrumb($breadcrumb);
				unset($breadcrumb);
				$content = $this->printTemplate('orders.php', array_merge(array(
					'login' => $logged,
					'is_delivered' => $is_delivered
				), $is_delivered ? array(
					'orders' => Order::getUserOrders(null, array('status' => Order::STATUS_DELIVERED))
				) : array(
					'orders' => Order::getUserOrders(null, array('status' => Order::STATUS_OPEN)),
					'delivering_orders' => Order::getUserOrders(null, array('status' => Order::STATUS_DELIVERING)),
					'delivered_orders' => Order::getUserOrders(null, array('status' => Order::STATUS_DELIVERED, 'limit' => 5))
				)));
				$new_content = \Hook::run('gastmo_print_orders_html_content', array($content, $is_delivered), true);
				if (is_string($new_content)) {
					$content = $new_content;
				}
				unset($new_content);
				return array(
					'title' => $title,
					'content' => $content,
					'url' => $url,
					'head_include' => $head_include
				);
			}
		}
	}
	
	/**
	 * Salva i dati dell'ordine di un utente
	 * @access private
	 */
	private function saveCart() {
		$ok = false;
		if (isset($_POST['sent'])) {
			$check = \Hook::run('gastmo_order_save_cart_json_check', array(true, $this->getShippableOrder()), true);
			$msg = null;
			if (is_array($check)) {
				$msg = isset($check['msg']) && is_string($check['msg']) && trim($check['msg']) !== '' ? $check['msg'] : null;
				$check = isset($check['check']) ? $check['check'] : null;
			}
			if (!is_bool($check) || $check === true) {
				$ok = Order::saveCart(
					\User::getLoggedUser(),
					$this->getShippableOrder(),
					isset($_POST['products']) && is_array($_POST['products']) ? $_POST['products'] : array(),
					$msg
				);
			}
			unset($check);
		}
		$this->printJson(array('ok' => $ok ? 1 : 0, 'msg' => $msg));
		unset($ok);
	}
	
	/**
	* Restituisce l'ID dell'ordine attuale acquistabile
	* @access private
	* @param boolean $returnArray stabilisce se restituire l'ID in un array anche se l'ordine non � acquistabile
	* @return mixed
	*/
	private function getShippableOrder($returnArray=false){
		$k = isset($this->params_url[1]) && $this->params_url[1]=='save' ? 2 : 1;
		if(isset($this->params_url[$k])) {
			list($order) = explode('-',$this->params_url[$k]);
			$order = is_numeric($order) && $order>0 ? (int)$order : 0;
		} else {
			$order = 0;
		}
		$res = $returnArray ? array($order,true) : $order;
		if(!Order::isShippable($order)){
			if($returnArray){
				$res[1] = false;
			} else {
				$res = false;
			}
		}
		return $res;
	}
	
	/**
	* Restituisce i prodotti selezionati di un ordine in JSON
	* @access private
	*/
	private function getOrderedProducts() {
		$json = array('ok' => 0);
		if (isset($_POST['sent'])) {
			$products = Order::getUserOrderedProducts(
				isset($this->params_url[2]) ? $this->params_url[2] : null,
				\User::getLoggedUser(),
				false
			);
			if (is_array($products)) {
				$json['ok'] = 1;
				$json['products'] = $products;
			}
			unset($products);
		}
		echo json_encode($json);
		unset($json);
		exit();
	}
	
	/**
	* Salva i voti di un ordine
	* @access private
	* @param Order $order oggetto dell'ordine
	* @return boolean
	*/
	private function saveVotes($order) {
		if (is_object($order) && $order instanceof Order) {
			$res = Order::saveCartVotes(
				\User::getLoggedUser(),
				$order->get('id'),
				isset($_POST['product_vote']) ? $_POST['product_vote'] : null,
				isset($_POST['product_vote_descr']) ? $_POST['product_vote_descr'] : null,
				isset($_POST['product_waste']) ? $_POST['product_waste'] : null
			);
			if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
				echo json_encode(array('ok' => $res ? 1 : 0));
				exit();
			} else {
				redirect(Order::getURL($order));
			}
		}
		return false;
	}
}