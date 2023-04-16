<?php
/**
 * Order Class
 * 
 * Questo file contiene la classe Order che serve a gestire gli ordini
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
 * La classe Order contiene tutti i metodi per gestire gli ordini
 */
class Order extends \Base {
	const STATUS_OPEN = 0;
	const STATUS_DELIVERING = 1;
	const STATUS_DELIVERED = 2;
	
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $id ID dell'ordine
	 */
	public function __construct($id = 0) {
		$this->table = 'orders';
		$this->fields = array(
			'id' => array('type' => 'autoincrement', 'key' => 'primary'),
			'title' => array('type' => 'text', 'title' => 'Titolo', 'check' => array('string')),
			'descr' => array('type' => 'text', 'title' => 'Note'),
			'closing_date' => array('type' => 'datetime', 'title' => 'Chiusura ordine', 'default' => ''),
			'shipping_date' => array('type' => 'date', 'title' => 'Data distribuzione'),
			'admin' => array('type' => 'integer', 'title' => 'Referente', 'check' => array('number')),
			'user_group' => array('type' => 'integer', 'title' => 'Gruppo', 'check' => array('number')),
			'shipping_cost' => array('type' => 'number', 'title' => 'Spese di spedizione', 'check' => array('number'), 'default' => 0),
			'export' => array('type' => 'text', 'title' => 'Esportazione', 'default' => ''),
			'export_date' => array('type' => 'integer', 'title' => 'Esportazione', 'check' => array('number'), 'default' => 0),
			'status' => array('type' => 'integer', 'title' => 'Stato', 'check' => array('number'), 'default' => 0),
			'online' => array('type' => 'integer', 'title' => 'Online', 'check' => array('number'), 'default' => 0)
		);
		$this->setByParams(array('id' => $id));
		parent::__construct();
	}
	
	/**
	 * Crea un ordine
	 * @access public
	 * @param array $data dati
	 * @param array $files files da caricare
	 * @return boolean|array
	 */
	public function create($data, $files = array()) {
		$res = parent::create($data, $files);
		if ($res === true) {
			$res = $this->importCsv($files, $data);
		}
		return $res;
	}
	
	/**
	 * Modifica l'ordine
	 * @access public
	 * @param array $data dati
	 * @param array $files files da caricare
	 * @return boolean|array
	 */
	public function edit($data, $files = array()) {
		$res = parent::edit($data, $files);
		if ($res === true) {
			$res = $this->importCsv($files, $data);
		}
		return $res;
	}
	
	/**
	 * Cancella l'ordine
	 * @access public
	 * @return boolean
	 */
	public function delete() {
		$res = false;
		$id = $this->get('id');
		if (self::canDelete($id)) {
			$res = parent::delete();
			if ($res) {
				$this->deleteChildren($id);
			}
		}
		unset($id);
		return $res;
	}
	
	/**
	 * Cancella tutti i valori delle altre tabelle relative a un ordine
	 * @access public
	 * @param integer $id ID dell'ordine
	 * @return boolean
	 */
	private function deleteChildren($id) {
		return is_numeric($id) && $id > 0
			&& \DB::writerQuery('del', 'carts', null, array(array('field' => 'order', 'value' => $id)))
			&& \DB::writerQuery('del', 'categories', null, array(array('field' => 'order', 'value' => $id)))
			&& \DB::writerQuery('del', 'products', null, array(array('field' => 'category', 'match' => 'NOT IN', 'value' => '(SELECT id FROM categories)', 'value_type' => 'sql')))
			&& \DB::writerQuery('del', 'orders_export', null, array(array('field' => 'order', 'value' => $id)));
	}
	
	/**
	 * Stabilisce se può essere modificato il valore di "online" di un ordine
	 * @access public
	 * @static
	 * @param integer|Order|array $order ID, oggetto o dati dell'ordine
	 * @return boolean
	 */
	public static function canEditOnline($order) {
		$can = \Hook::run('gastmo_order_can_edit_online', array(true, $order), true);
		return is_bool($can) ? $can : true;
	}
	
	/**
	 * Stabilisce se un ordine può essere cancellato
	 * @access public
	 * @static
	 * @param integer|Order|array $order ID, oggetto o dati dell'ordine
	 * @return boolean
	 */
	public static function canDelete($order) {
		$can = \Hook::run('gastmo_order_can_delete', array(true, $order), true);
		return is_bool($can) ? $can : true;
	}
	
	/**
	 * Importazione di categorie e prodotti nell'ordine
	 * @access private
	 * @param array $files file inviati dal form dell'ordine
	 * @param array $data dati inviati dal form dell'ordine
	 * @return boolean|array
	 */
	private function importCsv($files, $data = array()) {
		if ($this->exists() && is_array($files) && isset($files['csv']) && is_string($files['csv']) && trim($files['csv']) != '' && file_exists($files['csv'])) {
			if (!is_array($data)) {
				$data = array();
			}
			$id = (int)$this->get('id');
			$this->deleteChildren($id);
			$pt = new ProductType();
			$types = $pt->getList();
			unset($pt);
			$counter_types = count($types);
			$category = 0;
			$pos = array('category' => 0, 'product' => 0);
			$csv_style = array(
				'delimiter' => isset($data['csv_delimiter']) && is_string($data['csv_delimiter']) && trim($data['csv_delimiter']) != '' ? trim($data['csv_delimiter']) : ';',
				'enclosure' => isset($data['csv_enclosure']) && is_string($data['csv_enclosure']) && trim($data['csv_enclosure']) != '' ? trim($data['csv_delimiter']) : '"', 
			);
			$f = fopen($files['csv'], 'r');
			while ($r = fgetcsv($f, 0, $csv_style['delimiter'], $csv_style['enclosure'])) {
				switch ($this->checkCsvRow($r)) {
					case 'category':
						$d = array(
							'title' => trim(checkUTF8($r[0])),
							'order' => $id,
							'pos' => ++$pos['category']
						);
						$c = new Category();
						if ($c->create($d) === true) {
							$category = $c->get('id');
						} else {
							return array('Errore nell\'importazione del CSV.');
						}
						unset($c, $d);
					break;
					case 'product':
						$ok = false;
						if ($category > 0) {
							$title = trim(checkUTF8($r[0]));
							$type = 0;
							for ($i = 0; $i < $counter_types; $i++) {
								$c = '#'.trim($types[$i]['slug']);
								$cl = strlen($c);
								if (substr($title, -$cl) == $c) {
									$title = substr($title, 0, -$cl);
									$type = (int)$types[$i]['id'];
									break;
								}
								unset($c, $cl);
							}
							unset($i);
							$d = array(
								'title' => $title,
								'category' => $category,
								'qty_package' => (float)(str_replace(',', '.', $r[3])),
								'um' => trim(checkUTF8($r[4])),
								'price' => $this->getCsvPrice($r[5]),
								'maker' => trim(checkUTF8($r[6])),
								'location' => trim(checkUTF8($r[7])),
								'vat' => (int)$r[8],
								'package_price' => $this->getCsvPrice($r[9]),
								'note' => trim(checkUTF8($r[10])),
								'type' => $type,
								'pos' => ++$pos['product']
							);
							$p = new Product();
							if ($p->create($d) === true) {
								$ok = true;
							}
							unset($p, $d);
						}
						if (!$ok) {
							return array('Errore nell\'importazione del CSV.');
						}
						unset($ok);
					break;
				}
				unset($r);
			}
			fclose($f);
			unset($f, $pos, $category, $counter_types, $types, $id);
		}
		return true;
	}
	
	/**
	 * Controlla se una riga del CSV di importazione è di una categoria o di un prodotto
	 * @access private
	 * @param array $r dati della riga
	 * @return string
	 */
	private function checkCsvRow($r){
		$t = null;
		if (is_array($r) && isset($r[0]) && is_string($r[0]) && trim($r[0]) != '') {
			$is_product = true;
			for ($i = 3; $i <= 5; $i++) {
				if (!isset($r[$i]) || !is_string($r[$i]) || trim($r[$i]) == '') {
					$is_product = false;
					break;
				}
			}
			unset($i);
			$t = $is_product ? 'product' : 'category';
			unset($is_product);
		}
		return $t;
	}
	
	/**
	 * Restituisce i campi standard del file CSV
	 * @access public
	 * @static
	 * @param array $params parametri vari
	 * @return array
	 */
	public static function getCsvDefaultFields($params = array()) {
		$fields = array(
			array(
				'field' => 'title',
				'label' => 'Nome',
				'pos' => 0
			),
			array(
				'field' => '',
				'label' => '',
				'pos' => 1
			),
			array(
				'field' => '',
				'label' => '',
				'pos' => 2
			),
			array(
				'field' => 'qty_package',
				'label' => 'Quantità collo',
				'pos' => 3
			),
			array(
				'field' => 'um',
				'label' => 'Unità di misura',
				'pos' => 4
			),
			array(
				'field' => 'price',
				'label' => 'Prezzo',
				'pos' => 5
			),
			array(
				'field' => 'maker',
				'label' => 'Produttore',
				'pos' => 6
			),
			array(
				'field' => 'location',
				'label' => 'Località',
				'pos' => 7
			),
			array(
				'field' => 'vat',
				'label' => 'IVA',
				'pos' => 8
			),
			array(
				'field' => 'package_price',
				'label' => 'Prezzo collo',
				'pos' => 9
			),
			array(
				'field' => 'note',
				'label' => 'Note',
				'pos' => 10
			)
		);
		if (is_array($params)) {
			if (isset($params['empty_label']) && is_string($params['empty_label']) && trim($params['empty_label']) !== '') {
				foreach ($fields as $k => $v) {
					if ($v['field'] === '') {
						$fields[$k]['label'] = $params['empty_label'];
					}
				}
			}
		}
		return $fields;
	}
	
	/**
	 * Restituisce il valore di un prezzo del CSV di importazione
	 * @access protected
	 * @param string $v valore
	 * @return float
	 */
	protected function getCsvPrice($v) {
		return is_scalar($v) && trim($v) !== '' ? floatval(preg_replace('/([^0-9.])/', '', str_replace(',', '.', $v))) : 0.0;
	}
	
	/**
	 * Salva il carrello di un utente
	 * @access public
	 * @static
	 * @param integer $user ID dell'utente
	 * @param integer $order ID dell'ordine
	 * @param array $products elenco dei prodotti
	 * @param string $msg eventuali messaggi
	 * @return boolean
	 */
	public static function saveCart($user, $order, $products, &$msg = null) {
		$res = false;
		$msg = null;
		if (is_numeric($user) && $user > 0 && is_numeric($order) && $order > 0 && is_array($products) && self::isShippable($order, $user)) {
			$in_transaction = \DB::transaction('in_transaction');
			if (!$in_transaction) {
				\DB::transaction('begin');
			}
			try {
				$check = \Hook::run('gastmo_order_save_cart_begin', array(true, $user, $order, $products), true);
				if (is_array($check)) {
					$msg = isset($check['msg']) && is_string($check['msg']) && trim($check['msg']) !== '' ? $check['msg'] : null;
					$check = isset($check['check']) ? $check['check'] : null;
				}
				if (!is_null($check) && !$check) {
					throw new \Exception('Errore nell\'hook d\'inizio.');
				}
				unset($check);
				
				if (!\DB::writerQuery('del', 'carts', null, array(
					array('field' => 'order', 'value' => $order),
					array('field' => 'user', 'value' => $user)
				))) {
					throw new \Exception('Errore nella cancellazione dei vecchi valori.');
				}
				$fields = array();
				if (!empty($products)) {
					foreach ($products as $p => $q) {
						if (is_numeric($p) && $p > 0) {
							$q = self::getInputQty($q);
							if ($q > 0) {
								$fields[] = array(
									'order' => $order,
									'product' => $p,
									'user' => $user,
									'qty' => $q
								);
							}
						}
						unset($p, $q);
					}
				}
				if (!empty($fields) && !\DB::writerQuery('insmul', 'carts', $fields)) {
					throw new \Exception('Errore nel salvataggio dei nuovi valori.');
				}
				unset($fields);
				
				$check = \Hook::run('gastmo_order_save_cart_end', array(true, $user, $order, $products), true);
				if (is_array($check)) {
					$msg = isset($check['msg']) && is_string($check['msg']) && trim($check['msg']) !== '' ? $check['msg'] : null;
					$check = isset($check['check']) ? $check['check'] : null;
				}
				if (!is_null($check) && !$check) {
					throw new \Exception('Errore nell\'hook di fine.');
				}
				unset($check);
				
				if (!$in_transaction) {
					\DB::transaction('commit');
				}
				$res = true;
			} catch (\Exception $e) {
				if ($in_transaction) {
					throw $e;
				} else {
					\DB::transaction('rollback');
				}
				$res = false;
			}
			unset($in_transaction);
		}
		return $res;
	}
	
	/**
	 * Salva un prodotto del carrello di un utente
	 * @access public
	 * @static
	 * @param integer $user ID dell'utente
	 * @param integer $order ID dell'ordine
	 * @param integer $product ID del prodotto
	 * @param float $qty quantità
	 * @param boolean $is_admin stabilisce se la modifica avviene dal pannello d'amministrazione
	 * @return boolean
	 */
	public static function saveCartProduct($user, $order, $product, $qty, $is_admin = false) {
		$res = false;
		if (is_numeric($user) && $user > 0 && is_numeric($order) && $order > 0 && is_numeric($product) && $product > 0 && ($is_admin || self::isShippable($order, $user))) {
			$qty = self::getInputQty($qty);
			if ($qty > 0) {
				$res = \DB::writerQuery('rep', 'carts', array(
					'order' => $order,
					'product' => $product,
					'user' => $user,
					'qty' => $qty
				));
			} else {
				$res = \DB::writerQuery('del', 'carts', null, array(
					array('field' => 'user', 'value' => $user),
					array('field' => 'order', 'value' => $order),
					array('field' => 'product', 'value' => $product)
				));
			}
		}
		return $res;
	}
	
	/**
	 * Salva i voti di un utente
	 * @access public
	 * @static
	 * @param integer $user ID dell'utente
	 * @param integer $order ID dell'ordine
	 * @param array $votes voti
	 * @param array $descr eventuali note
	 * @return boolean
	 */
	public static function saveCartVotes($user, $order, $votes, $descrs = array(), $wastes = array()) {
		$res = false;
		if (is_numeric($user) && $user > 0 && is_numeric($order) && $order > 0) {
			$res = true;
			$saved_products = array();
			if (!is_array($descrs)) {
				$descrs = array();
			}
			if (is_array($votes) && !empty($votes)) {
				$label = self::getLabelVotes();
				foreach ($votes as $k => $v) {
					if (is_numeric($k) && $k > 0 && is_numeric($v) && $v >= 1 && isset($label[$v])) {
						$k = (int)$k;
						if (\DB::writerQuery(
							'upd',
							'carts',
							array(
								'vote' => intval($v),
								'vote_descr' => isset($descrs[$k]) && is_string($descrs[$k]) ? trim($descrs[$k]) : null,
								'waste' => isset($wastes[$k]) ? self::getInputQty($wastes[$k]) : 0
							),
							array(
								array('field' => 'user', 'value' => $user),
								array('field' => 'order', 'value' => $order),
								array('field' => 'product', 'value' => $k)
							)
						)) {
							$saved_products[] = $k;
						} else {
							$res = false;
						}
					}
					unset($k, $v);
				}
				unset($label);
			}
			if (!empty($descrs)) {
				foreach ($descrs as $k => $v) {
					if (is_numeric($k) && $k > 0 && is_string($v) && !in_array($k, $saved_products)) {
						if (!\DB::writerQuery(
							'upd',
							'carts',
							array(
								'vote_descr' => trim($v)
							),
							array(
								array('field' => 'user', 'value' => $user),
								array('field' => 'order', 'value' => $order),
								array('field' => 'product', 'value' => $k)
							)
						)) {
							$res = false;
						}
					}
					unset($k, $v);
				}
			}
			if (!empty($wastes)) {
				foreach ($wastes as $k => $v) {
					if (is_numeric($k) && $k > 0 && !in_array($k, $saved_products)) {
						if (!\DB::writerQuery(
							'upd',
							'carts',
							array(
								'waste' => self::getInputQty($v)
							),
							array(
								array('field' => 'user', 'value' => $user),
								array('field' => 'order', 'value' => $order),
								array('field' => 'product', 'value' => $k)
							)
						)) {
							$res = false;
						}
					}
					unset($k, $v);
				}
			}
			unset($saved_products);
		}
		return $res;
	}
	
	/**
	 * Restituisce i voti possibili con la descrizione
	 * @access public
	 * @static
	 * @return array
	 */
	public static function getLabelVotes() {
		return array(
			1 => 'Reclamo',
			2 => 'Qualche problema',
			3 => 'Nella media',
			4 => 'Buono',
			5 => 'Eccellenza'
		);
	}
	
	/**
	 * Restitusce il valore della quantità
	 * @access protected
	 * @static
	 * @param mixed $q quantità
	 * @return float
	 */
	protected static function getInputQty($q) {
		if (is_scalar($q) && trim($q) != '') {
			$q = str_replace(',', '.', $q);
		}
		return is_numeric($q) && $q > 0 ? (float)$q : 0;
	}
	
	/**
	 * Restituisce gli utenti di un ordine
	 * @access public
	 * @static
	 * @param integer $id ID dell'ordine
	 * @param boolean $all_data stabilisce se restituire tutti i dati degli utenti e non solo gli ID
	 * @return array
	 */
	public static function getUsers($id, $all_data = false) {
		$users = array();
		if (is_numeric($id) && $id > 0) {
			$sql = 'SELECT user FROM ('.\DB::createSql(array(
				'select' => array('user'),
				'table' => 'carts',
				'where' => array(
					array('field' => 'order', 'value' => $id)
				)
			)).' UNION '.\DB::createSql(array(
				'select' => array('user'),
				'table' => 'orders_totals',
				'where' => array(
					array('field' => 'order', 'value' => $id)
				)
			)).') AS x GROUP BY user';
			if ($all_data) {
				$user = new \UserView();
				$users = $user->getList(array(
					'where' => array(
						array('field' => 'id', 'match' => 'IN', 'value' => '('.$sql.')', 'value_type' => 'sql')
					)
				));
				unset($user);
			} else {
				$users = \DB::queryCol($sql);
			}
			unset($sql);
		}
		return $users;
	}
	
	/**
	 * Restituisce i prodotti di un ordine di un utente
	 * @access public
	 * @static
	 * @param integer $id ID dell'ordine
	 * @param integer $user ID dell'utente (se 0, utilizza quello dell'utente loggato)
	 * @param boolean $all_data stabilisce se restituire tutti i dati dei prodotti e non solo gli ID
	 * @return array
	 */
	public static function getUserOrderedProducts($id, $user = 0, $all_data = true) {
		$products = array();
		$order = new Order($id);
		if ($order->exists()) {
			if (!is_numeric($user) || $user <= 0) {
				$user = User::getLoggedUser();
			}
			if (is_numeric($user) && $user > 0) {
				$sql = \DB::createSql(array(
					'select' => array('product'),
					'table' => 'carts',
					'where' => array(
						array('field' => 'user', 'value' => $user),
						array('field' => 'order', 'value' => $order->get('id'))
					)
				));
				if ($all_data) {
					$p = new Product();
					$products = $p->getList(array(
						'where' => array(
							array('field' => 'id', 'match' => 'IN', 'value' => '('.$sql.')', 'value_type' => 'sql')
						)
					));
					unset($p);
				} else {
					$products = \DB::queryCol($sql);
				}
				unset($sql);
			}
		}
		unset($order);
		return $products;
	}
	
	/**
	 * Restituisce gli ordini di un utente
	 * @access public
	 * @static
	 * @param integer $user ID dell'utente (se 0, utilizza quello dell'utente loggato)
	 * @param array $params parametri vari
	 * @return array
	 */
	public static function getUserOrders($user = 0, $params = array()) {
		if (!is_numeric($user) || $user <= 0) {
			$user = \User::getLoggedUser();
		}
		if (is_numeric($user) && $user > 0) {
			if (!is_array($params)) {
				$params = array();
			}
			$sql = array(
				'where' => array(
					array('field' => 'online', 'value' => 1)
				),
				'order' => array(
					'shipping_date' => 'DESC',
					'closing_date' => 'DESC'
				)
			);
			if (isset($params['status']) && is_numeric($params['status'])) {
				$sql['where'][] = array('field' => 'status', 'value' => $params['status']);
				switch ($params['status']) {
					case Order::STATUS_OPEN:
						$sql['select'] = array(
							array('value' => '*', 'no_quote' => true),
							array('value' => 'IF (closing_date = \'0000-00-00 00:00:00\', \'a\', closing_date)', 'no_quote' => true, 'as' => 'closing_date_order')
						);
						$sql['order'] = array(
							'closing_date_order' => 'ASC',
							'shipping_date' => 'ASC'
						);
					break;
					case Order::STATUS_DELIVERING:
						$sql['select'] = array(
							array('value' => '*', 'no_quote' => true),
							array('value' => 'IF (shipping_date = \'0000-00-00\', \'a\', shipping_date)', 'no_quote' => true, 'as' => 'shipping_date_order')
						);
						$sql['order'] = array(
							'shipping_date_order' => 'ASC',
							'closing_date' => 'ASC'
						);
					break;
				}
			}
			if (isset($params['id_order'])) {
				$params['id_order'] = is_array($params['id_order']) ? array_values($params['id_order']) : array($params['id_order']);
				$counter = count($params['id_order']);
				for ($i = 0; $i < $counter; $i++) {
					if (is_numeric($params['id_order'][$i]) && $params['id_order'][$i] > 0) {
						$params['id_order'][$i] = intval($params['id_order'][$i]);
					} else {
						unset($params['id_order'][$i]);
					}
				}
				unset($i, $counter);
				$params['id_order'][] = 0;
				$sql['where'][] = array('field' => 'id', 'match' => 'IN', 'value' => $params['id_order']);
			}
			if (isset($params['limit']) && is_numeric($params['limit']) && $params['limit'] > 0) {
				$sql['limit'] = $params['limit'];
			}
			$o = new Order();
			$orders = $o->getList($sql);
			unset($o, $sql);
			$counter = count($orders);
			for ($i = 0; $i < $counter; $i++) {
				$orders[$i]['user_ordered'] = self::hasUserOrdered($user, $orders[$i]['id']);
				$orders[$i]['url'] = self::getURL($orders[$i]);
			}
			unset($i, $counter);
		} else {
			$orders = array();
		}
		return $orders;
	}
	
	/**
	 * Stabilisce se un utente ha fatto un ordine
	 * @access public
	 * @static
	 * @param integer $user ID dell'utente
	 * @param integer $order ID dell'ordine
	 * @return booolean
	 */
	public static function hasUserOrdered($user, $order) {
		$res = false;
		if (is_numeric($user) && $user > 0 && is_numeric($order) && $order > 0) {
			\DB::prepare('user_ordered', \DB::createSql(array(
				'select' => array(
					array('value' => 'COUNT(*)', 'no_quote' => true)
				),
				'table' => 'carts',
				'where' => array(
					array('field' => 'user', 'value' => '?', 'value_type' => 'sql'),
					array('field' => 'order', 'value' => '?', 'value_type' => 'sql')
				),
				'limit' => 1
			)));
			$num = \DB::queryOne(\DB::execPrepared('user_ordered', array($user, $order)));
			$res = is_numeric($num) && $num > 0;
			unset($num);
		}
		return $res;
	}
	
	/**
	 * Restituisce gli ordini gestibili da un utente
	 * @access public
	 * @static
	 * @param integer $user ID dell'utente (se 0, utilizza quello dell'utente loggato)
	 * @param string $return stabilisce il tipo di dato da restituire
	 * @return array
	 */
	public static function getUserManagedOrders($user = 0, $return = null) {
		$orders = array();
		if (!is_numeric($user) || $user <= 0) {
			$user = \User::getLoggedUser();
		}
		if (is_numeric($user) && $user > 0) {
			$u = new \User($user);
			if (in_array($u->get('level'), array('admin', 'subadmin', 'gestione_ordini', 'contabile'))) {
				$sql = array(
					'order' => array('id' => 'DESC')
				);
				if ($u->get('level') == 'gestione_ordini') {
					$groups = UserGroup::getUserGroups($u->get('id'), 'id');
					$groups[] = 0;
					$sql['where'] = array(array('field' => 'user_group', 'match' => 'IN', 'value' => $groups));
					unset($groups);
				}
				$o = new Order();
				$orders = $o->getList($sql);
				unset($o, $sql);
				if (is_string($return) && trim($return) != '') {
					$arr = $orders;
					$orders = array();
					$counter = count($arr);
					for ($i = 0; $i < $counter; $i++) {
						if (isset($arr[$i][$return])) {
							$orders[] = $arr[$i][$return];
						}
					}
					unset($i, $counter, $arr);
				}
			}
			unset($u);
		}
		return $orders;
	}
	
	/**
	 * Restituisce l'indirizzo di un ordine
	 * @param mixed $order oggetto, dati o ID di un ordine
	 * @return string
	 */
	public static function getURL($order) {
		if (is_numeric($order) && $order > 0) {
			$order = new Order($order);
		}
		if (is_object($order)) {
			$order = $order->getData();
		}
		if (is_array($order) && isset($order['id']) && is_numeric($order['id']) && $order['id'] > 0 
			&& isset($order['title']) && is_scalar($order['title']) && trim($order['title']) != '') {
			return '/order/'.intval($order['id']).'-'.createLink($order['title']).'/';
		}
		return null;
	}
	
	/**
	 * Stabilisce se un ordine è acquistabile
	 * @access public
	 * @static
	 * @param integer $id ID dell'ordine
	 * @param integer $user ID dell'utente (se 0, utilizza quello dell'utente loggato)
	 * @param boolean $was_shippable stabilisce di non controllare se l'ordine è in consegna
	 * @return boolean
	 */
	public static function isShippable($id, $user = 0, $was_shippable = false) {
		$res = false;
		if (is_numeric($id) && $id > 0) {
			$orders = self::getUserOrders($user);
			$counter = count($orders);
			for ($i = 0; $i < $counter; $i++) {
				if ($orders[$i]['id'] == $id) {
					$res = $was_shippable || $orders[$i]['status'] == self::STATUS_OPEN;
					break;
				}
			}
			unset($i, $counter, $orders);
		}
		return $res;
	}
	
	/**
	 * Verifica che un prodotto esista all'interno dell'ordine
	 * @access public
	 * @param integer $id_product ID del prodotto
	 * @return boolean
	 */
	public function checkProductExists($id_product) {
		static $products = array();
		$check = false;
		if ($this->exists() && is_numeric($id_product) && $id_product > 0) {
			$id_order = $this->get('id');
			$id_product = (int)$id_product;
			if (!isset($products[$id_order])) {
				$products[$id_order] = array();
			}
			if (!isset($products[$id_order][$id_product])) {
				\DB::prepare('order_product_exists', \DB::createSql(array(
					'select' => array(
						array('value' => 'COUNT(*)', 'no_quote' => true)
					),
					'table' => 'products',
					'join' => array(
						'categories' => array('categories.id', 'products.category')
					),
					'where' => array(
						array('field' => 'categories.order', 'value' => '?', 'value_type' => 'sql'),
						array('field' => 'products.id', 'value' => '?', 'value_type' => 'sql')
					)
				)));
				$num = \DB::queryOne(\DB::execPrepared('order_product_exists', array($id_order, $id_product)));
				$products[$id_order][$id_product] = is_numeric($num) && $num > 0;
				unset($num);
			}
			$check = $products[$id_order][$id_product];
			unset($id_order, $id_product);
		}
		return $check;
	}
	
	/**
	 * Restituisce il totale di un ordine per un utente
	 * @access public
	 * @param integer $id_user ID dell'utente
	 * @param integer $id_order ID dell'ordine (se non specificato, si utilizza quello dell'oggetto)
	 * @param boolean $is_actual_qty stabilisce se si tratta della quantità effettiva
	 * @return float
	 */
	public function getUserTotal($id_user, $id_order = null, &$is_actual_qty = false) {
		$tot = 0.0;
		$is_actual_qty = false;
		if (is_numeric($id_user) && $id_user > 0) {
			$id_order = is_numeric($id_order) && $id_order > 0 ? intval($id_order) : ($this->exists() ? intval($this->get('id')) : null);
			if ($id_order) {
				$status = $this->exists() && $this->get('id') == $id_order ? $this->get('status') : self::getOneData($id_order, 'status');
				if ($status == self::STATUS_OPEN) {
					$prepared = 'gastmo_order_get_user_total_open_carts';
					\DB::prepare($prepared, \DB::createSql(array(
						'select' => array(
							array('value' => 'SUM(carts.qty * products.price)', 'no_quote' => true)
						),
						'table' => 'carts',
						'join' => array(
							'products' => array('products.id', 'carts.product')
						),
						'where' => array(
							array('field' => 'carts.user', 'value' => '?', 'value_type' => 'sql'),
							array('field' => 'carts.order', 'value' => '?', 'value_type' => 'sql'),
							array('field' => 'carts.qty', 'match' => 'IS NOT')
						)
					)));
					$tot_res = \DB::queryOne(\DB::execPrepared($prepared, array($id_user, $id_order)));
					unset($prepared);
					if (is_numeric($tot_res)) {
						$tot = floatval($tot_res);
					}
					unset($tot_res);
				} else {
					$prepared = 'gastmo_order_get_user_total';
					if (!\DB::preparedExists($prepared)) {
						\DB::prepare($prepared, \DB::createSql(array(
							'select' => array('total'),
							'table' => 'orders_totals',
							'where' => array(
								array('field' => 'order', 'value' => '?', 'value_type' => 'sql'),
								array('field' => 'user', 'value' => '?', 'value_type' => 'sql')
							),
							'limit' => 1
						)));
					}
					$tot_res = \DB::queryOne(\DB::execPrepared($prepared, array($id_order, $id_user)));
					unset($prepared);
					if (is_numeric($tot_res)) {
						$tot = floatval($tot_res);
						$is_actual_qty = true;
					} else {
						$prepared = 'gastmo_order_get_user_total_carts';
						if (!\DB::preparedExists($prepared)) {
							\DB::prepare($prepared, 'SELECT SUM(total) FROM ('.\DB::createSql(array(
								'select' => array(
									array('value' => 'SUM(carts.actual_qty * products.price)', 'no_quote' => true, 'as' => 'total')
								),
								'table' => 'carts',
								'join' => array(
									'products' => array('products.id', 'carts.product')
								),
								'where' => array(
									array('field' => 'carts.user', 'value' => '?', 'value_type' => 'sql'),
									array('field' => 'carts.order', 'value' => '?', 'value_type' => 'sql'),
									array('field' => 'carts.actual_qty', 'match' => 'IS NOT')
								)
							)).' UNION '.\DB::createSql(array(
								'select' => array(
									array('value' => 'SUM(qty * price)', 'no_quote' => true, 'as' => 'total')
								),
								'table' => 'carts_extras',
								'where' => array(
									array('field' => 'user', 'value' => '?', 'value_type' => 'sql'),
									array('field' => 'order', 'value' => '?', 'value_type' => 'sql')
								)
							)).') AS x');
						}
						$tot_res = \DB::queryOne(\DB::execPrepared($prepared, array($id_user, $id_order, $id_user, $id_order)));
						unset($prepared);
						if (is_numeric($tot_res)) {
							$tot = floatval($tot_res);
							$is_actual_qty = true;
						} else {
							$oe = new OrderExporter($id_order);
							$json = @json_decode($oe->getCurrentExport('json'), true);
							unset($oe);
							if (is_array($json) && isset($json['qty']) && is_array($json['qty']) && !empty($json['qty']) && isset($json['qty'][$id_user]) && is_array($json['qty'][$id_user]) && !empty($json['qty'][$id_user])) {
								$products_prices = array();
								foreach ($json['qty'][$id_user] as $id_product => $q) {
									if (is_numeric($id_product) && $id_product > 0 && is_numeric($q) && $q > 0) {
										$id_product = intval($id_product);
										if (!isset($products_prices[$id_product])) {
											$p = new Product($id_product);
											$products_prices[$id_product] = floatval($p->get('price'));
											unset($p);
										}
										$tot += floatval($q) * $products_prices[$id_product];
									}
									unset($id_product, $q);
								}
								unset($products_prices);
							}
							unset($json);
						}
					}
					unset($tot_res);
				}
				unset($status);
			}
		}
		return $tot;
	}
}

/**
 * La classe OrderExporter contiene tutti i metodi per gestire le esportazioni degli ordini
 */
class OrderExporter extends Order {
	const PERC_PACKAGE = 65;
	
	/**
	 * Restituisce i carrelli ordinati per prodotto dell'ordine
	 * @access public
	 * @param integer $date eventuale UNIX Timestamp della data dell'esportazione
	 * @return array
	 */
	public function getCarts($date = null) {
		$carts = \DB::queryRows(\DB::createSql(array(
			'select' => array(
				array('value' => 'users.id', 'as' => 'user_id'),
				array('value' => 'users.username', 'as'=> 'username'),
				'users.email',
				array('value' => 'CONCAT(users_data.name, \' \', users_data.surname)', 'as'=> 'name', 'no_quote' => true),
				'users_data.phone',
				array('value' => 'products.id', 'as' => 'product_id'),
				array('value' => 'products.title', 'as' => 'product_name'),
				array('value' => 'carts.qty', 'as' => 'product_quantity'),
				array('value' => 'products.um', 'as' => 'product_um'),
				array('value' => 'products.price', 'as' => 'product_price'),
				'products.qty_package',
				array('value' => 'products.note', 'as' => 'product_note')
			),
			'table' => 'carts',
			'join' => array(
				'users' => array('cond' => array('users.id', 'carts.user')),
				'users_data' => array('cond' => array('users_data.id', 'users.id'), 'type' => 'left'),
				'products' => array('cond' => array('products.id', 'carts.product'))
			),
			'where' => array(array('field' => 'carts.order', 'value' => $this->get('id'))),
			'order' => array('products.id' => 'ASC', 'carts.qty' => 'DESC', 'users.id' => 'ASC')
		)));
		$carts = is_array($carts) ? $carts : array();
		$json = is_null($date) ? null : $this->getExport('json', $date);
		$export = $json ? @json_decode($json, true) : null;
		unset($json);
		if ($export) {
			$counter = count($carts);
			for ($i = 0; $i < $counter; $i++) {
				$carts[$i]['product_old_quantity'] = $carts[$i]['product_quantity'];
				$carts[$i]['product_quantity'] = isset($export['qty'][$carts[$i]['user_id']]) && isset($export['qty'][$carts[$i]['user_id']][$carts[$i]['product_id']])
					? $export['qty'][$carts[$i]['user_id']][$carts[$i]['product_id']]
					: 0;
			}
			unset($i, $counter);
		}
		$products = $users = array();
		$counter = count($carts);
		for ($i = 0; $i < $counter; $i++) {
			$carts[$i]['product_id'] = (int)$carts[$i]['product_id'];
			if (!in_array($carts[$i]['product_id'], $products)) {
				$products[] = $carts[$i]['product_id'];
			}
			$carts[$i]['user_id'] = (int)$carts[$i]['user_id'];
			if (!in_array($carts[$i]['user_id'], $users)) {
				$users[] = $carts[$i]['user_id'];
			}
		}
		unset($i, $counter);
		$counter = count($products);
		for ($i = 0; $i < $counter; $i++) {
			if ($export) {
				$carts = $this->checkProductPackages(
					$products[$i],
					$carts,
					true,
					isset($export['packages'][$products[$i]]) ? $export['packages'][$products[$i]] : 0,
					null,
					$users
				);
			} else {
				$carts = $this->checkProductPackages($products[$i], $carts, true);
			}
		}
		unset($i, $counter, $products, $users, $export);
		return $carts;
	}
	
	/**
	 * Aggiorna i carrelli con i dati delle cassette di ogni prodotto
	 * @param integer $product ID del prodotto
	 * @param array $carts carrelli
	 * @param boolean $return_all stabilisce se restituire anche i carrelli che non sono del prodotto
	 * @param integer $tot_packages numero di cassette
	 * @param float $round arrotondamento
	 * @param array $fixed_carts ID degli utenti dei carrelli da mantenere fissi
	 * @return array
	 */
	public function checkProductPackages($product, $carts, $return_all = false, $tot_packages = null, $round = null, $fixed_carts = array()) {
		if (!is_numeric($product) || $product <= 0 || !is_array($carts)) {
			$carts = array();
		}
		if (!empty($carts)) {
			if (is_array($fixed_carts) && !empty($fixed_carts)) {
				foreach ($fixed_carts as $k => $v) {
					if (is_numeric($v) && $v > 0) {
						$fixed_carts[$k] = (int)$v;
					} else {
						unset($fixed_carts[$k]);
					}
					unset($k, $v);
				}
				$fixed_carts = array_values(array_unique($fixed_carts));
			} else {
				$fixed_carts = array();
			}
			$tot = 0;
			$fixed_qty = 0;
			$qty_package = 1;
			$um = null;
			$f = true;
			foreach ($carts as $k => $v) {
				if ($v['product_id'] == $product) {
					if ($f) {
						$qty_package = (float)$v['qty_package'];
						$um = $v['product_um'];
						$f = false;
					}
					$tot += $v['product_quantity'];
					if (in_array($v['user_id'], $fixed_carts)) {
						$fixed_qty += $v['product_quantity'];
					}
				} else if (!$return_all) {
					unset($carts[$k]);
				}
				unset($k, $v);
			}
			unset($f);
			$d = $tot / $qty_package;
			$perc_packages = $d * 100;
			if (is_numeric($tot_packages) && $tot_packages >= 0) {
				$tot_packages = (int)$tot_packages;
			} else {
				$tot_packages = fmod($tot, $qty_package) * 100 / $qty_package >= self::PERC_PACKAGE ? ceil($d) : floor($d);
			}
			if ($fixed_qty > 0) {
				$d -= $fixed_qty / $qty_package;
				$div = $d > 0 ? ($tot_packages - $fixed_qty / $qty_package) / $d : 0;
			} else {
				$div = $d > 0 ? $tot_packages / $d : 0;
			}
			unset($d, $fixed_qty);
			$round = is_numeric($round) && $round > 0 ? (float)$round : ($um == 'KG' ? 0.1 : 1);
			unset($um);
			$round_mul = 1 / $round;
			$qty_total = 0;
			reset($carts);
			foreach ($carts as $k => $v) {
				if ($v['product_id'] == $product) {
					$carts[$k]['packages'] = $tot_packages;
					$carts[$k]['round'] = $round;
					if (in_array($v['user_id'], $fixed_carts)) {
						$carts[$k]['product_new_quantity'] = (float)$v['product_quantity'];
						if (isset($v['product_old_quantity']) && is_numeric($v['product_old_quantity']) && $v['product_old_quantity'] >= 0) {
							$carts[$k]['product_quantity'] = $v['product_quantity'] = (float)$v['product_old_quantity'];
						}
					} else {
						$carts[$k]['product_new_quantity'] = round($v['product_quantity'] * $div * $round_mul) / $round_mul;
					}
					$qty_total += $carts[$k]['product_new_quantity'];
					$carts[$k]['product_diff_quantity'] = $carts[$k]['product_new_quantity'] - $v['product_quantity'];
					$carts[$k]['perc_packages'] = $perc_packages;
					$carts[$k]['tot_qty_diff'] = 0;
				}
				unset($k, $v);
			}
			unset($perc_packages, $div, $round_mul);
			if ($qty_package == 0) {
				$qty_package = 1;
			}
			$check_qty_diff_mult = $qty_package >= 1 ? 1 : 1 / $qty_package;
			if (fmod($qty_total, $qty_package) != 0) {
				$diff = round($qty_total - $tot_packages * $qty_package, 2);
				reset($carts);
				foreach ($carts as $k => $v) {
					if ($v['product_id'] == $product) {
						$carts[$k]['tot_qty_diff'] = $diff;
					}
					unset($k, $v);
				}
				unset($diff);
			}
			unset($check_qty_diff_mult, $qty_package);
		}
		return array_values($carts);
	}
	
	/**
	 * Restituisce il CSV degli ordini degli utenti
	 * @access public
	 * @return string
	 */
	public function exportCartsCSV() {
		$orders = $this->exists() ? \DB::queryRows(\DB::createSql(array(
			'select' => array(
				array('value' => 'users.id', 'as' => 'user_id'),
				array('value' => 'users.username', 'as' => 'username'),
				array('value' => 'users.id', 'as' => 'order_id'),
				array('value' => 'products.id', 'as' => 'product_sku'),
				array('value' => 'products.title', 'as' => 'product_name'),
				array('value' => 'carts.qty', 'as' => 'product_quantity'),
				array('value' => 'products.um', 'as' => 'product_unit'),
				array('value' => 'products.price', 'as' => 'product_price'),
				array('value' => 'products.qty_package', 'as' => 'product_packaging'),
				array('value' => 'carts.actual_qty', 'as' => 'product_actual_quantity')
			),
			'table' => 'carts',
			'join' => array(
				'users' => array('cond' => array('users.id', 'carts.user')),
				'products' => array('cond' => array('products.id', 'carts.product'))
			),
			'where' => array(array('field' => 'carts.order', 'value' => $this->get('id'))),
			'order' => array('users.id' => 'ASC')
		))) : array();
		$counter = count($orders);
		if ($counter > 0) {
			$float_fields = array('product_quantity', 'product_price', 'product_packaging', 'product_actual_quantity');
			$counter_f = count($float_fields);
			for ($i = 0; $i < $counter; $i++) {
				for ($j = 0; $j < $counter_f; $j++) {
					if (isset($orders[$i][$float_fields[$j]]) && is_numeric($orders[$i][$float_fields[$j]])) {
						$orders[$i][$float_fields[$j]] = (float)$orders[$i][$float_fields[$j]];
					}
				}
				unset($j);
			}
			unset($i, $counter_f, $float_fields);
		}
		unset($counter);
		return $this->arrayToCsv($orders);
	}
	
	/**
	 * Restituisce il codice HTML dell'esportazione
	 * @access public
	 * @param array $qty quantità di ogni utente per ogni prodotto
	 * @param array $packages numero di cassette per ogni prodotto
	 * @param boolean $do_save stabilisce se salvare l'esportazione
	 * @return string
	 */
	public function exportHTML($qty, $packages, $do_save = true) {
		$o = '';
		$carts = $this->getCarts();
		$counter = count($carts);
		if ($counter > 0 && is_array($qty) && !empty($qty) && is_array($packages) && !empty($packages)) {
			$tot_packages = 0;
			$tot_price = 0;
			$products = array();
			$users = array();
			for ($i = 0; $i < $counter; $i++) {
				if (isset($packages[$carts[$i]['product_id']]) && !isset($products[$carts[$i]['product_id']])) {
					$product_tot_price = $packages[$carts[$i]['product_id']] * $carts[$i]['qty_package'] * $carts[$i]['product_price'];
					$products[$carts[$i]['product_id']] = array(
						'title' => $carts[$i]['product_name'],
						'um' => $carts[$i]['product_um'],
						'qty_package' => $carts[$i]['qty_package'],
						'price' => $carts[$i]['product_price'],
						'qty' => $packages[$carts[$i]['product_id']] * $carts[$i]['qty_package'],
						'tot_packages' => $packages[$carts[$i]['product_id']],
						'tot_price' => $product_tot_price
					);
					$tot_packages += $packages[$carts[$i]['product_id']];
					$tot_price += $product_tot_price;
					unset($product_tot_price);
				}
				if (isset($qty[$carts[$i]['user_id']]) && is_array($qty[$carts[$i]['user_id']]) && isset($qty[$carts[$i]['user_id']][$carts[$i]['product_id']]) && is_numeric($qty[$carts[$i]['user_id']][$carts[$i]['product_id']]) && $qty[$carts[$i]['user_id']][$carts[$i]['product_id']] >= 0) {
					if (!isset($users[$carts[$i]['user_id']])) {
						$users[$carts[$i]['user_id']] = array(
							'username' => $carts[$i]['username'],
							'email' => $carts[$i]['email'],
							'name' => $carts[$i]['name'],
							'phone' => $carts[$i]['phone'],
							'tot' => 0,
							'products' => ''
						);
					}
					$q = floatval($qty[$carts[$i]['user_id']][$carts[$i]['product_id']]);
					$tot = $q * $carts[$i]['product_price'];
					$users[$carts[$i]['user_id']]['tot'] += $tot;
					$users[$carts[$i]['user_id']]['products'] .= '<tr'.($q == 0 ? ' class="qty_zero"' : '').'>'
						.'<td class="title">'.html($carts[$i]['product_name']).'</td>'
						.'<td>'.html($carts[$i]['product_um']).'</td>'
						.'<td>'.$q.'</td>'
						.'<td>'.printMoney($carts[$i]['product_price']).'</td>'
						.'<td>'.printMoney($tot).'</td>'
						.'</tr>';
					unset($tot, $q);
				}
			}
			unset($i);
			
			// Spese di spedizione
			$multiplier = $this->getExportShippingCostMultiplier($users);
			if ($multiplier > 0) {
				foreach ($users as $k => $v) {
					$val = $users[$k]['tot'] * $multiplier;
					$users[$k]['tot'] += $val;
					$users[$k]['products'] .= printHtmlTag(
						'tr', 
						printHtmlTag('td', 'Spese di spedizione', array('class' => 'title'))
						.printHtmlTag('td', '')
						.printHtmlTag('td', '')
						.printHtmlTag('td', '')
						.printHtmlTag('td', printMoney($val))
					);
					unset($val, $k, $v);
				}
				unset($tot);
			}
			unset($multiplier);
			
			$products_o = '';
			$no_products_o = '';
			foreach ($products as $k => $v) {
				$product_o = '<tr>'
					.'<td>'.html($k).'</td>'
					.'<td class="title">'.html($v['title']).'</td>'
					.'<td>'.html($v['tot_packages']).'</td>'
					.'<td>'.html($v['um']).'</td>'
					.'<td>'.html($v['qty_package']).'</td>'
					.'<td>'.printMoney($v['price']).'</td>'
					.'<td>'.printMoney($v['tot_price']).'</td>'
					.'</tr>';
				if ($v['tot_packages'] > 0) {
					$products_o .= $product_o;
				} else {
					$no_products_o .= $product_o;
				}
				unset($product_o, $k, $v);
			}
			unset($products);
			$o .= '<table>
				<thead>
					<tr>
						<th colspan="2" class="title">Ordine</th>
						<th>'.$tot_packages.'</th>
						<th colspan="3"></th>
						<th>'.printMoney($tot_price).'</th>
					</tr>
					<tr>
						<th>SKU</th>
						<th class="title">Prodotto</th>
						<th>Cassette da ordinare</th>
						<th>UM</th>
						<th>Kg o Pz per cassetta</th>
						<th>Prezzo</th>
						<th>Importo</th>
					</tr>
				</thead>
				<tbody>'.$products_o;
			unset($tot_packages, $tot_price, $products_o);
			if ($no_products_o != '') {
				$o .= '<tr class="thead"><td colspan="7" class="title">Prodotti non ordinati</td></tr>'.$no_products_o;
			}
			unset($no_products_o);
			$o .= '</tbody>
			</table>';
			$new_users = \Hook::run('gastmo_export_order_html_check_users', array($users, $this));
			if (is_array($new_users)) {
				$users = $new_users;
			}
			unset($new_users);
			foreach ($users as $k => $v) {
				$o .= '<table>
					<thead>
						<tr>
							<th class="title">Prodotto</th>
							<th>UM</th>
							<th>Q.t&agrave;</th>
							<th>Prezzo</th>
							<th>Totale</th>
						</tr>
					</thead>
					<tfoot>
						<tr>
							<td colspan="4" class="title">'.(html(trim($v['name']) == '' ? $v['username'] : $v['name'])).' - '.html($v['email']).' - '.html($v['phone']).'</td>
							<td>'.printMoney($v['tot']).'</td>
						</tr>
					</tfoot>
					<tbody>'.$v['products'].'</tbody>
				</table>';
				unset($k, $v);
			}
			unset($users);
		}
		unset($counter, $carts);
		if ($o == '') {
			$o = '<p>Ci sono stati dei problemi nell&rsquo;esportazione.</p>';
		}
		$module = \Modules::getModule('Gastmo');
		if ($module) {
			$tpl = $module['dir'].'templates/order_export.html';
			if (is_file($tpl)) {
				$o = $o = str_replace('{CONTENT}', $o, file_get_contents($tpl));
			}
			unset($tpl);
		}
		unset($module);
		if ($do_save) {
			$this->saveExport($qty, $packages, $o, $this->exportCSV($qty, $packages, false));
		}
		return $o;
	}
	
	/**
	 * Restituisce il CSV dell'esportazione
	 * @param array $qty quantità di ogni utente per ogni prodotto
	 * @param array $packages numero di cassette per ogni prodotto
	 * @param boolean $do_save stabilisce se salvare l'esportazione
	 * @return string
	 */
	private function exportCSV($qty, $packages, $do_save = true) {
		$sql = array(
			'select' => array(
				array('value' => 'users.id', 'as' => 'user_id'),
				array('value' => 'users.username', 'as' => 'username'),
				array('value' => 'CONCAT(users_data.name, \' \', users_data.surname)', 'as' => 'name', 'no_quote' => true),
				array('value' => 'users_data.phone', 'as' => 'phone'),
				array('value' => 'users.id', 'as' => 'order_id'),
				array('value' => 'products.id', 'as' => 'product_sku'),
				array('value' => 'products.title', 'as' => 'product_name'),
				array('value' => 'products.maker', 'as' => 'product_maker'),
				array('value' => 'carts.qty', 'as' => 'product_quantity'),
				array('value' => 'products.um', 'as' => 'product_unit'),
				array('value' => 'products.price', 'as' => 'product_price'),
				array('value' => 'products.qty_package', 'as' => 'product_packaging')
			),
			'table' => 'carts',
			'join' => array(
				'users' => array('cond' => array('users.id', 'carts.user')),
				'users_data' => array('cond' => array('users_data.id', 'users.id'), 'type' => 'left'),
				'products' => array('cond' => array('products.id', 'carts.product'))
			),
			'where' => array(array('field' => 'carts.order', 'value' => $this->get('id'))),
			'order' => array('users.id' => 'ASC')
		);
		$new_sql = \Hook::run('exportOrderCSVCheckSql', array($sql, $this));
		if ($new_sql) {
			$sql = $new_sql;
		}
		unset($new_sql);
		$carts = \DB::queryRows(\DB::createSql($sql));
		if (is_array($carts)) {
			if (!is_array($qty)) {
				$qty = array();
			}
			$counter = count($carts);
			for ($i = 0; $i < $counter; $i++) {
				if (isset($qty[$carts[$i]['user_id']]) && is_array($qty[$carts[$i]['user_id']]) && isset($qty[$carts[$i]['user_id']][$carts[$i]['product_sku']]) && is_numeric($qty[$carts[$i]['user_id']][$carts[$i]['product_sku']]) && $qty[$carts[$i]['user_id']][$carts[$i]['product_sku']] >= 0) {
					$carts[$i]['product_quantity'] = (float)$qty[$carts[$i]['user_id']][$carts[$i]['product_sku']];
					$carts[$i]['product_price'] = (float)$carts[$i]['product_price'];
					$carts[$i]['product_packaging'] = (float)$carts[$i]['product_packaging'];
				} else {
					unset($carts[$i]);
				}
			}
			unset($i, $counter);
		} else {
			$carts = array();
		}
		$new_carts = \Hook::run('exportOrderCSVCheckCarts', array($carts, $this));
		if (is_array($new_carts)) {
			$carts = $new_carts;
		}
		unset($new_carts);
		$csv = $this->arrayToCsv($carts);
		unset($carts);
		if ($do_save) {
			$this->saveExport($qty, $packages, $this->exportHTML($qty, $packages, false), $csv);
		}
		return $csv;
	}
	
	/**
	 * Restituisce il file Excel dell'esportazione
	 * @param array $qty quantità di ogni utente per ogni prodotto
	 * @param array $packages numero di cassette per ogni prodotto
	 * @param string $type tipo di esportazione (default: normale, 2: verticale)
	 * @return string
	 */
	private function exportXLSX($qty, $packages, $type = null) {
		$excel = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
		$excel->getProperties()
			->setTitle($this->get('title'))
			->setCreator('Gastmo')
			->setLastModifiedBy('Gastmo');
		$carts = $this->getCarts();
		$counter = count($carts);
		if ($counter > 0 && is_array($qty) && !empty($qty) && is_array($packages) && !empty($packages)) {
			$products = $users = array();
			for ($i = 0; $i < $counter; $i++) {
				if (isset($packages[$carts[$i]['product_id']]) && !isset($products[$carts[$i]['product_id']])) {
					$packages[$carts[$i]['product_id']] = isset($packages[$carts[$i]['product_id']]) && is_numeric($packages[$carts[$i]['product_id']])
						? $packages[$carts[$i]['product_id']]
						: 0;
					$products[$carts[$i]['product_id']] = array(
						'title' => $carts[$i]['product_name'],
						'um' => $carts[$i]['product_um'],
						'qty_package' => $carts[$i]['qty_package'],
						'price' => $carts[$i]['product_price'],
						'qty' => $packages[$carts[$i]['product_id']] * $carts[$i]['qty_package'],
						'tot_packages' => $packages[$carts[$i]['product_id']],
						'tot_price' => $packages[$carts[$i]['product_id']] * $carts[$i]['qty_package'] * $carts[$i]['product_price']
					);
				}
				if (isset($qty[$carts[$i]['user_id']]) && is_array($qty[$carts[$i]['user_id']]) && isset($qty[$carts[$i]['user_id']][$carts[$i]['product_id']]) && is_numeric($qty[$carts[$i]['user_id']][$carts[$i]['product_id']]) && $qty[$carts[$i]['user_id']][$carts[$i]['product_id']] >= 0) {
					if (!isset($users[$carts[$i]['user_id']])) {
						$users[$carts[$i]['user_id']] = array(
							'username' => $carts[$i]['username'],
							'email' => $carts[$i]['email'],
							'name' => $carts[$i]['name'],
							'phone' => $carts[$i]['phone'],
							'tot' => 0,
							'products' => array()
						);
					}
					$q = floatval($qty[$carts[$i]['user_id']][$carts[$i]['product_id']]);
					$users[$carts[$i]['user_id']]['tot'] += $q * $carts[$i]['product_price'];
					$users[$carts[$i]['user_id']]['products'][] = array_merge($carts[$i], array(
						'product_new_quantity' => $q
					));
					unset($q);
				}
			}
			unset($i);
			
			// Spese di spedizione
			$multiplier = $this->getExportShippingCostMultiplier($users);
			if ($multiplier > 0) {
				foreach ($users as $k => $v) {
					$val = $users[$k]['tot'] * $multiplier;
					$users[$k]['tot'] += $val;
					$users[$k]['products'][] = array(
						'product_id' => 'XT',
						'product_name' => 'Spese di spedizione',
						'product_um' => '',
						'product_quantity' => 1,
						'product_new_quantity' => 1,
						'product_price' => $val
					);
					unset($val, $k, $v);
				}
				unset($tot);
			}
			unset($multiplier);
			
			// Varie
			foreach ($users as $k => $v) {
				$users[$k]['products'][] = array(
					'product_id' => 'XV',
					'product_name' => 'Varie',
					'product_um' => '',
					'product_quantity' => 0,
					'product_new_quantity' => 0,
					'product_price' => 1
				);
				unset($k, $v);
			}
			
			$new_users = \Hook::run('gastmo_export_order_xlsx_check_users', array($users, $this));
			if (is_array($new_users)) {
				$users = $new_users;
			}
			unset($new_users);
			switch (is_scalar($type) ? $type : null) {
				case 2:
					$sheet = $excel->getActiveSheet()->setTitle('Dettaglio ordine');
					
					$styles = array(
						'borders' => array(
							'borders' => array(
								'outline' => array(
									'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
									'color' => array('rgb' => '008000')
								)
							)
						),
						'big_title' => array(
							'alignment' => array(
								'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
								'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER
							),
							'font' => array(
								'bold' => true,
								'size' => 15
							)
						),
						'header' => array(
							'fill' => array(
								'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
								'startColor' => array('rgb' => '92D050')
							)
						),
						'ordered' => array(
							'fill' => array(
								'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
								'startColor' => array('rgb' => 'EBF1DE')
							)
						),
						'to_order' => array(
							'fill' => array(
								'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
								'startColor' => array('rgb' => 'FFF1C4')
							)
						),
						'tot_order' => array(
							'fill' => array(
								'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
								'startColor' => array('rgb' => 'FFC7C9')
							)
						)
					);
					foreach (array('header', 'ordered', 'to_order', 'tot_order') as $v) {
						$styles[$v] = array_merge($styles[$v], $styles['borders'], array(
							'alignment' => array(
								'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
								'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
								'wrap' => true
							)
						));
						unset($v);
					}
					
					$sheet->getColumnDimension('A')->setWidth(75);
					$sheet->getRowDimension('2')->setRowHeight(30);
					$sheet->setCellValue('A2', _SITETITLE);
					$sheet->getStyle('A2')->applyFromArray(array_merge($styles['big_title'], array(
						'fill' => array(
							'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
							'startColor' => array('rgb' => 'FFFF00')
						)
					)));
					$sheet->getRowDimension('4')->setRowHeight(30);
					$sheet->setCellValue('A4', 'Prodotto');
					$sheet->setCellValue('B4', 'Prezzo per UM');
					$sheet->setCellValue('C4', 'UM');
					$sheet->setCellValue('D4', 'Colli da ordinare');
					
					$products_rows = array();
					$row = 5;
					foreach ($products as $k => $v) {
						$sheet->setCellValue('A'.$row, $v['title']);
						$sheet->setCellValue('B'.$row, $v['price']);
						$sheet->setCellValue('C'.$row, $v['um']);
						$sheet->setCellValue('D'.$row, $v['tot_packages']);
						$sheet->getStyle('D'.$row)->applyFromArray($styles['ordered']);
						$products_rows[$k] = $row;
						$row++;
						unset($k, $v);
					}
					$sheet->getStyle('B5:B'.$row)->getNumberFormat()->setFormatCode('[$€ ]#,##0.00_-');
					unset($row);
					
					$tot_row = max($products_rows) + 1;
					$sheet->getStyle('A4:C'.($tot_row - 1))->applyFromArray($styles['borders']);
					
					$num = 1;
					$col = 5;
					foreach ($users as $k => $v) {
						$col_1 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 1);
						$col_2 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
						$col_3 = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1);
						
						$sheet->mergeCells($col_1.'2:'.$col_3.'2');
						$sheet->setCellValue($col_1.'2', $num);
						$sheet->getStyle($col_1.'2')->applyFromArray($styles['big_title']);
						
						$sheet->mergeCells($col_1.'3:'.$col_3.'3');
						$sheet->setCellValue($col_1.'3', $v['name']);
						
						$sheet->setCellValue($col_1.'4', 'Ordinato');
						$sheet->getStyle($col_1.'4:'.$col_1.($tot_row - 1))->applyFromArray($styles['ordered']);
						$sheet->setCellValue($col_2.'4', 'Peso');
						$sheet->getStyle($col_2.'4:'.$col_2.($tot_row - 1))->applyFromArray($styles['to_order']);
						$sheet->setCellValue($col_3.'4', 'Valore €');
						$sheet->getStyle($col_3.'4:'.$col_3.($tot_row - 1))->applyFromArray($styles['tot_order']);
						
						foreach ($v['products'] as $v2) {
							if (isset($products_rows[$v2['product_id']])) {
								$sheet->setCellValue($col_1.$products_rows[$v2['product_id']], $v2['product_new_quantity']);
							}
							unset($v2);
						}
						foreach ($products_rows as $v2) {
							$sheet->setCellValue($col_3.$v2, '=IF(ISBLANK('.$col_2.$v2.'),"",PRODUCT(B'.$v2.','.$col_2.$v2.'))');
							unset($v2);
						}
						$sheet->getStyle($col_3.'5:'.$col_3.$tot_row)->getNumberFormat()->setFormatCode('[$€ ]#,##0.00_-');
						$sheet->setCellValue($col_3.$tot_row, '=SUM('.$col_3.'5:'.$col_3.($tot_row - 1).')');
						$sheet->getStyle($col_3.$tot_row)->applyFromArray($styles['borders']);
						$sheet->getColumnDimension($col_1)->setWidth(10);
						$sheet->getColumnDimension($col_2)->setWidth(10);
						$sheet->getColumnDimension($col_3)->setWidth(10);
						unset($col_1, $col_2, $col_3);
						$col += 3;
						$num++;
						unset($k, $v);
					}
					unset($num);
					
					$sheet->getStyle('A4:D4')->applyFromArray($styles['header']);
					$sheet->getStyle('E3:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 3).'3')->applyFromArray($styles['header']);
					$sheet->setCellValue('D'.$tot_row, 'Totali');
					$sheet->getStyle('D'.$tot_row)->applyFromArray($styles['header']);
					$sheet->getStyle('B5:'.\PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col - 2).$tot_row)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
					unset($col, $tot_row, $styles);
					
					$excel->getActiveSheet()->freezePane('E5');
				break;
				case 't':
					$sheet = $excel->getActiveSheet()->setTitle('Persone');
					$sheet
						->setCellValue('A1', 'ID')
						->setCellValue('B1', 'Utente')
						->setCellValue('C1', 'Totale')
						->setCellValue('D1', 'Totale previsto');
					$x_cols = array();
					foreach ($users as $k => $v) {
						foreach ($v['products'] as $v2) {
							if (is_string($v2['product_id']) && substr($v2['product_id'], 0, 1) == 'X' && $v2['product_new_quantity'] > 0 && !isset($x_cols[$v2['product_id']])) {
								$x_cols[$v2['product_id']] = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex((empty($x_cols)
									? 5
									: \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString(max($x_cols))));
								$sheet->setCellValue($x_cols[$v2['product_id']].'1', $v2['product_name']);
							}
							unset($v2);
						}
						unset($k, $v);
					}
					foreach (array_merge(array('C', 'D'), empty($x_cols) ? array() : array_merge(array('E'), $x_cols)) as $v) {
						$sheet->getColumnDimension($v)->setWidth(15);
						unset($v);
					}
					if (!empty($x_cols)) {
						$sheet->setCellValue('E1', 'Totale prodotti');
					}
					$sheet->getStyle('A1:'.(empty($x_cols) ? 'D' : max($x_cols)).'1')->applyFromArray(array(
						'alignment' => array(
							'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER,
							'vertical' => \PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER,
							'wrap' => true
						),
						'font' => array(
							'bold' => true
						)
					));
					$sheet->getRowDimension(1)->setRowHeight(25);
					$row = 1;
					foreach ($users as $k => $v) {
						$row++;
						$tot = $tot_products = 0;
						foreach ($v['products'] as $v2) {
							$total_price = $v2['product_new_quantity'] * $v2['product_price'];
							$tot += $total_price;
							if (isset($x_cols[$v2['product_id']])) {
								$sheet->setCellValue($x_cols[$v2['product_id']].$row, $total_price);
							} else {
								$tot_products += $total_price;
							}
							unset($total_price);
						}
						$sheet
							->setCellValue('A'.$row, $k)
							->setCellValue('B'.$row, (trim($v['name']) == '' ? $v['username'] : $v['name']).' - '.$v['email'].' - '.$v['phone'])
							->setCellValue('D'.$row, $tot);
						if (!empty($x_cols)) {
							$sheet->setCellValue('E'.$row, $tot_products);
						}
						unset($k, $v);
					}
					$sheet->getColumnDimension('B')->setAutoSize(true);
					$sheet->getProtection()->setSheet(true);
					$sheet->getStyle('C2:C'.$row)->applyFromArray(array(
						'fill' => array(
							'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
							'startColor' => array('rgb' => 'FFFF99')
						)
					))->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
					$sheet->getStyle('C2:'.(empty($x_cols) ? 'D' : max($x_cols)).$row)->getNumberFormat()->setFormatCode('[$€ ]#,##0.00_-');
					unset($x_cols, $row, $sheet);
				break;
				default:
					$sheet = $excel->getActiveSheet()->setTitle('Persone');
					$products_sheet = $excel->createSheet()->setTitle('Prodotti');
					$products_sheet
						->setCellValue('A1', 'SKU')
						->setCellValue('B1', 'Prodotto')
						->setCellValue('C1', 'UM')
						->setCellValue('D1', 'Kg o Pz totali')
						->setCellValue('E1', 'Scarto')
						->setCellValue('F1', 'Prezzo')
						->setCellValue('G1', 'Importo')
						->setCellValue('H1', 'Giudizio')
						->setCellValue('I1', 'Note');
					$products_sheet->getStyle('A1:I1')->getFont()->setBold(true);
					$row = 2;
					$products_sheet_rows = array();
					foreach ($products as $k => $v) {
						$products_sheet
							->setCellValue('A'.$row, $k)
							->setCellValue('B'.$row, $v['title'])
							->setCellValue('C'.$row, $v['um'])
							->setCellValue('D'.$row, $v['tot_packages'] * $v['qty_package'])
							->setCellValue('F'.$row, $v['price'])
							->setCellValue('G'.$row, '=D'.$row.'*F'.$row);
						foreach (array('D'.$row.':F'.$row, 'H'.$row.':I'.$row) as $cells) {
							$products_sheet->getStyle($cells)->applyFromArray(array(
								'fill' => array(
									'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
									'startColor' => array('rgb' => 'FFFF99')
								)
							))->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
							unset($cells);
						}
						$products_sheet->getStyle('F'.$row.':G'.$row)->getNumberFormat()->setFormatCode('[$€ ]#,##0.00_-');
						$products_sheet_rows[$k] = $row;
						$row++;
						unset($k, $v);
					}
					$products_sheet
						->setCellValue('D'.$row, '=SUM(D2:D'.($row - 1).')')
						->setCellValue('F'.$row, '=SUM(G2:G'.($row - 1).')');
					$products_sheet->getStyle('G'.$row)->getNumberFormat()->setFormatCode('[$€ ]#,##0.00_-');
					$products_sheet->getColumnDimension('B')->setAutoSize(true);
					$products_sheet->getProtection()->setSheet(true);
					$row = 1;
					foreach ($users as $k => $v) {
						$first_row = $row;
						$sheet
							->setCellValue('A'.$row, 'ID')
							->setCellValue('B'.$row, 'Prodotto')
							->setCellValue('C'.$row, 'UM')
							->setCellValue('D'.$row, 'Q.ta ordinata')
							->setCellValue('E'.$row, 'Q.ta ripartita')
							->setCellValue('F'.$row, 'Q.ta effettiva')
							->setCellValue('G'.$row, 'Prezzo')
							->setCellValue('H'.$row, 'Totale');
						$sheet->getStyle('A'.$row.':H'.$row)->getFont()->setBold(true);
						$row++;
						$counter_p = count($v['products']);
						for ($j = 0; $j < $counter_p; $j++) {
							$new_qty_div = isset($products_sheet_rows[$v['products'][$j]['product_id']])
								? $products_sheet->getCell('D'.$products_sheet_rows[$v['products'][$j]['product_id']])->getValue()
								: 0;
							$sheet
								->setCellValue('A'.$row, $v['products'][$j]['product_id'])
								->setCellValue('B'.$row, $v['products'][$j]['product_name'])
								->setCellValue('C'.$row, $v['products'][$j]['product_um'])
								->setCellValue('D'.$row, (float)$v['products'][$j]['product_quantity'])
								->setCellValue('E'.$row, isset($products_sheet_rows[$v['products'][$j]['product_id']]) && $v['products'][$j]['product_new_quantity'] > 0 && $new_qty_div > 0
									? '=ROUND((\'Prodotti\'!D'.$products_sheet_rows[$v['products'][$j]['product_id']].'-\'Prodotti\'!E'.$products_sheet_rows[$v['products'][$j]['product_id']].')*'.$v['products'][$j]['product_new_quantity'].'/'.$new_qty_div.', 2)'
									: $v['products'][$j]['product_new_quantity'])
								->setCellValue('G'.$row, isset($products_sheet_rows[$v['products'][$j]['product_id']])
									? '=\'Prodotti\'!F'.$products_sheet_rows[$v['products'][$j]['product_id']]
									: (float)$v['products'][$j]['product_price'])
								->setCellValue('H'.$row, '=F'.$row.'*G'.$row);
							unset($new_qty_div);
							if ($type == 'c') {
								$sheet->setCellValue('F'.$row, '=E'.$row);
							}
							if ($j % 2 == 0) {
								$sheet->getStyle('A'.$row.':H'.$row)->applyFromArray(array(
									'fill' => array(
										'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
										'startColor' => array('rgb' => 'CFE7F5')
									)
								));
							}
							$sheet->getStyle('F'.$row)->applyFromArray(array(
								'fill' => array(
									'fillType' => \PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID,
									'startColor' => array('rgb' => 'FFFF99')
								)
							))->getProtection()->setLocked(\PhpOffice\PhpSpreadsheet\Style\Protection::PROTECTION_UNPROTECTED);
							$sheet->getStyle('G'.$row.':H'.$row)->getNumberFormat()->setFormatCode('[$€ ]#,##0.00_-');
							$row++;
						}
						unset($j, $counter_p);
						$sheet
							->setCellValue('A'.$row, $k)
							->setCellValue('B'.$row, (trim($v['name']) == '' ? $v['username'] : $v['name']).' - '.$v['email'].' - '.$v['phone'])
							->mergeCells('B'.$row.':F'.$row)
							->setCellValue('G'.$row, '=SUMPRODUCT(E'.($first_row + 1).':E'.($row - 1).',G'.($first_row + 1).':G'.($row - 1).')')
							->setCellValue('H'.$row, '=SUM(H'.($first_row + 1).':H'.($row - 1).')');
						$sheet->getStyle('G'.$row.':H'.$row)->getNumberFormat()->setFormatCode('[$€ ]#,##0.00_-');
						$row += 2;
						unset($v);
					}
					unset($row, $products_sheet, $products_sheet_rows);
					$sheet->getColumnDimension('B')->setAutoSize(true);
					$sheet->getProtection()->setSheet(true);
					unset($sheet);
				break;
			}
			unset($products, $users);
		}
		unset($counter, $carts);
		$objWriter = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($excel, 'Xlsx');
		do {
			$f = _CACHE.'export_'.md5(mt_rand()).'.xlsx';
		} while (file_exists($f));
		$objWriter->save($f);
		unset($objWriter);
		$xlsx = file_get_contents($f);
		@unlink($f);
		unset($f);
		return $xlsx;
	}
	
	/**
	 * Restistuisce il moltiplicatore delle spese di trasporto nelle esportazioni
	 * @access private
	 * @param array $users valori degli utenti
	 * @return float
	 */
	private function getExportShippingCostMultiplier($users) {
		$multiplier = 0.0;
		$shipping_cost = $this->get('shipping_cost');
		if ($shipping_cost > 0) {
			$tot = 0;
			foreach ($users as $v) {
				$tot += $v['tot'];
				unset($v);
			}
			$multiplier = $shipping_cost / $tot;
			unset($tot);
		}
		unset($shipping_cost);
		return $multiplier;
	}
	
	/**
	 * Salva i dati dell'esportazione nel database
	 * @param array $qty quantità di ogni utente per ogni prodotto
	 * @param array $packages numero di cassette per ogni prodotto
	 * @param string $html esportazione in HTML
	 * @param string $csv esportazione in CSV
	 * @return boolean
	 */
	public function saveExport($qty, $packages, $html = '', $csv = '') {
		$res = false;
		if (is_array($qty) && !empty($qty) && is_array($packages) && !empty($packages)) {
			$res = \DB::writerQuery(
				'ins',
				'orders_export',
				array(
					'order' => $this->get('id'),
					'user' => \User::getLoggedUser(),
					'date' => date('Y-m-d H:i:s'),
					'json' => json_encode(array('qty' => $qty, 'packages' => $packages)),
					'html' => is_string($html) ? trim($html) : '',
					'csv' => is_string($csv) ? trim($csv) : ''
				)
			);
		}
		return $res;
	}
	
	/**
	 * Restituisce i dati dell'esportazione
	 * @param string $field campo da restituire (se non specificato, restituisce tutta la linea nel database)
	 * @param integer $date UNIX Timestamp della data dell'esportazione (se non specificato, restituisce l'ultima linea nel database)
	 * @return mixed
	 */
	public function getExport($field = '', $date = null) {
		$export = null;
		$exports = $this->getExports();
		$counter = count($exports);
		if ($counter > 0) {
			if (is_numeric($date) && $date > 0) {
				for ($i = 0; $i < $counter; $i++) {
					if (strtotime($exports[$i]['date']) == $date) {
						$export = $exports[$i];
						break;
					}
				}
				unset($i);
			} else {
				$export = $exports[count($exports) - 1];
			}
			if (is_array($export) && is_string($field) && trim($field) != '') {
				$export = isset($export[$field]) ? $export[$field] : null;
			}
		}
		unset($counter, $exports);
		return $export;
	}
	
	/**
	 * Restituisce i dati dell'esportazione corretta
	 * @param string $field campo da restituire (se non specificato, restituisce tutta la linea nel database)
	 * @return array
	 */
	public function getCurrentExport($field = '') {
		$export = $this->getExport($field, $this->get('export_date'));
		if (is_null($export)) {
			$export = $this->getExport($field);
		}
		return $export;
	}
	
	/**
	 * Scarica un'esportazione
	 * @param string $type tipo di esportazione
	 * @param integer $date UNIX Timestamp della data dell'esportazione (se non specificato, restituisce l'ultima linea nel database)
	 */
	public function downloadExport($type, $date = null) {
		$content = null;
		$mime = 'application/octet-stream';
		$ext = $type;
		switch (is_string($type) ? $type : null) {
			case 'csv':
				$content = $this->getExport('csv', $date);
				$mime = 'text/csv';
			break;
			case 'html':
				$content = $this->getExport('html', $date);
				$mime = 'text/html';
			break;
			case 'json':
				$json = $this->getExport('json', $date);
				if ($json) {
					$export = @json_decode($json, true);
					if ($export) {
						$content = json_encode($export);
						$mime = 'application/json';
					}
					unset($export);
				}
				unset($json);
			break;
			case 'xlsx':
			case 'xlsx2':
			case 'xlsxc':
			case 'xlsxt':
				$json = $this->getExport('json', $date);
				if ($json) {
					$export = @json_decode($json, true);
					if ($export) {
						$content = $this->exportXLSX($export['qty'], $export['packages'], $type === 'xlsx' ? null : substr($type, 4));
						$mime = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
						$ext = 'xlsx';
					}
					unset($export);
				}
				unset($json);
			break;
			default:
				$export = \Hook::run('gastmo_export_order_type', array($this, $type, $date));
				if (is_array($export)) {
					if (isset($export['content']) && \Valid::string($export['content'])) {
						$content = $export['content'];
					}
					if (isset($export['mime']) && \Valid::string($export['mime'])) {
						$mime = $export['mime'];
					}
					if (isset($export['ext']) && \Valid::string($export['ext'])) {
						$ext = $export['ext'];
					}
				}
				unset($export);
			break;
		}
		if ($content) {
			header('Content-Type: '.$mime);
			header('Content-Disposition: attachment; filename="'.str_replace(array('"', '/', '\\'), '', $this->get('title')).'.'.$ext.'"');
			header('Content-Transfer-Encoding: binary');
			header('Expires: 0');
			header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
			header('Pragma: public');
			header('Content-Length: '.strlen($content));
			echo $content;
			exit();
		}
		unset($content, $mime, $ext);
	}
	
	/**
	 * Scarica l'esportazione corretta
	 * @param string $type tipo di esportazione
	 */
	public function downloadCurrentExport($type) {
		$this->downloadExport($type, $this->getCurrentExport('date'));
	}
	
	/**
	 * Restituisce l'elenco delle esportazioni
	 * @return array
	 */
	public function getExports() {
		$exports = \DB::queryRows(\DB::createSql(array(
			'select' => array(
				array('value' => 'orders_export.*', 'no_quote' => true),
				'users.username',
				'users_data.surname',
				'users_data.name'
			),
			'table' => 'orders_export',
			'join' => array(
				'users' => array('cond' => array('users.id', 'orders_export.user'), 'type' => 'left'),
				'users_data' => array('cond' => array('users_data.id', 'users.id'), 'type' => 'left')
			),
			'where' => array(
				array('field' => 'orders_export.order', 'value' => $this->get('id'))
			),
			'order' => array('orders_export.date' => 'ASC')
		)));
		return is_array($exports) ? $exports : array();
	}
	
	/**
	 * Restituisce i tipi di esportazione
	 * @access public
	 * @return array
	 */
	public function getExportTypes() {
		$types = array(
			'html' => array('title' => 'HTML', 'check' => 'html'),
			'csv' => array('title' => 'CSV', 'check' => 'csv'),
			'xlsx' => array('title' => 'Excel', 'check' => 'json'),
			'xlsxc' => array('title' => 'Excel Precompilato', 'check' => 'json'),
			'xlsxt' => array('title' => 'Excel Totali', 'check' => 'json'),
			'xlsx2' => array('title' => 'Excel Verticale', 'check' => 'json')
		);
		$new_types = \Hook::run('gastmo_export_order_list', array($types, $this));
		if (is_array($new_types)) {
			$types = $new_types;
		}
		unset($new_types);
		return $types;
	}
	
	/**
	 * Rigenera i file dell'esportazione
	 * @param integer $date UNIX Timestamp della data dell'esportazione
	 * @return boolean
	 */
	public function refreshExport($date) {
		$res = false;
		if (is_numeric($date) && $date > 0) {
			$export = $this->getExport(null, $date);
			if ($export) {
				$json = @json_decode($export['json'], true);
				if (is_array($json) && isset($json['qty']) && isset($json['packages'])) {
					$res = \DB::writerQuery(
						'upd',
						'orders_export',
						array(
							'html' => $this->exportHTML($json['qty'], $json['packages'], false),
							'csv' => $this->exportCSV($json['qty'], $json['packages'], false)
						),
						array(
							array('field' => 'order', 'value' => $export['order']),
							array('field' => 'user', 'value' => $export['user']),
							array('field' => 'date', 'value' => $export['date'])
						)
					);
				}
				unset($json);
			}
			unset($export);
		}
		return $res;
	}
	
	/**
	 * Cancella un'esportazione
	 * @param integer $date UNIX Timestamp della data dell'esportazione
	 * @return boolean
	 */
	public function deleteExport($date) {
		$res = false;
		if (is_numeric($date) && $date > 0) {
			$res = \DB::writerQuery('delete', 'orders_export', null, array(
				array('field' => 'order', 'value' => $this->get('id')),
				array('field' => 'date', 'value' => date('Y-m-d H:i:s', $date))
			));
		}
		return $res;
	}
	
	/**
	 * Restituisce il CSV di un array
	 * @access private
	 * @param array $arr array
	 * @return string
	 */
	private function arrayToCsv($arr) {
		$arr = is_array($arr) ? array_values($arr) : array();
		$counter = count($arr);
		for ($i = 0; $i < $counter; $i++) {
			foreach ($arr[$i] as $k => $v) {
				if (is_float($v)) {
					$arr[$i][$k] = number_format($v, 3, ',', '.');
				}
				unset($k, $v);
			}
		}
		unset($i, $counter);
		return arrayToCsv($arr);
	}
	
	/**
	 * Importazione delle quantità definitive dell'ordine
	 * @access public
	 * @param string $file percorso del file CSV
	 * @param array $params parametri vari
	 * @return boolean
	 */
	public function importQty($file, $params = array()) {
		$res = false;
		if (is_string($file) && trim($file) != '' && is_file($file)) {
			if (!is_array($params)) {
				$params = array();
			}
			$csv_style = array(
				'delimiter' => isset($params['csv_delimiter']) && is_string($params['csv_delimiter']) && trim($params['csv_delimiter']) != '' ? trim($params['csv_delimiter']) : ';',
				'enclosure' => isset($params['csv_enclosure']) && is_string($params['csv_enclosure']) && trim($params['csv_enclosure']) != '' ? trim($params['csv_delimiter']) : '"', 
			);
			$last_id = null;
			$products = $extras = array();
			$f = fopen($file, 'r');
			while ($r = fgetcsv($f, 0, $csv_style['delimiter'], $csv_style['enclosure'])) {
				if (is_numeric($r[0]) && $r[0] > 0) {
					$last_id = (int)$r[0];
					if (isset($r[5]) && is_string($r[5]) && trim($r[5]) != '' && $this->checkProductExists($last_id)) {
						$products[$last_id] = floatval(str_replace(',', '.', $r[5]));
					}
				} else if (is_string($r[0]) && strlen($r[0]) > 1 && substr($r[0], 0, 1) == 'X') {
					$r[5] = floatval(str_replace(',', '.', $r[5]));
					$r[6] = $this->getCsvPrice($r[6]);
					$r[7] = $this->getCsvPrice($r[7]);
					$extras[] = $r;
				} else {
					if (!empty($products)) {
						if ($this->importQtyUser($last_id, $products, $extras)) {
							$res = true;
						}
						$products = $extras = array();
					}
				}
				unset($r);
			}
			unset($f, $csv_style);
			if (!empty($products) && $this->importQtyUser($last_id, $products, $extras)) {
				$res = true;
			}
			unset($products, $extras, $last_id);
			if ($res) {
				\DB::writerQuery('upd', $this->getTable(), array(
					'status' => isset($params['status']) && is_scalar($params['status']) && trim($params['status']) !== ''
						? $params['status']
						: self::STATUS_DELIVERED
				), array(
					array('field' => 'id', 'value' => $this->get('id'))
				));
			}
		}
		return $res;
	}
	
	/**
	 * Importazione delle quantità definitive dell'ordine di un singolo utente
	 * @access private
	 * @param integer $id_user ID dell'utente
	 * @param array $products prodotti con le quantità
	 * @param array $extras eventuali extra
	 * @return boolean
	 */
	private function importQtyUser($id_user, $products, $extras = array()) {
		$res = false;
		if (is_numeric($id_user) && $id_user > 0 && is_array($products) && !empty($products)) {
			$user = new \User($id_user);
			if ($user->exists()) {
				$in_transaction = \DB::transaction('in_transaction');
				if (!$in_transaction) {
					\DB::transaction('begin');
				}
				try {
					foreach ($products as $id_product => $qty) {
						if (!\DB::writerQuery('insdup', 'carts', array(
							'order' => array('value' => $this->get('id'), 'key' => true),
							'product' => array('value' => $id_product, 'key' => true),
							'user' => array('value' => $user->get('id'), 'key' => true),
							'actual_qty' => $qty
						))) {
							throw new Exception('Errore nel salvataggio di una voce del carrello.');
						}
						unset($id_product, $qty);
					}
					if (is_array($extras) && !empty($extras)) {
						foreach ($extras as $v) {
							if (isset($v[0]) && is_string($v[0]) && in_array($v[0], array('XT', 'XV'))) {
								if (!\DB::writerQuery('rep', 'carts_extras', array(
									'order' => $this->get('id'),
									'user' => $user->get('id'),
									'extra' => $v[0],
									'title' => isset($v[1]) && is_string($v[1]) ? $v[1] : null,
									'um' => isset($v[2]) && is_string($v[2]) ? $v[2] : null,
									'qty' => isset($v[5]) && is_numeric($v[5]) && $v[5] > 0 ? floatval($v[5]) : 0,
									'price' => isset($v[6]) && is_numeric($v[6]) && $v[6] > 0 ? floatval($v[6]) : 0
								))) {
									throw new Exception('Errore nel salvataggio di un extra.');
								}
							}
							unset($v);
						}
					}
					\Hook::run('gastmo_import_order_qty_extras', array($this, $user, $extras));
					if (!\DB::writerQuery('del', 'orders_totals', null, array(
						array('field' => 'order', 'value' => $this->get('id')),
						array('field' => 'user', 'value' => $user->get('id'))
					))) {
						throw new Exception('Errore nella cancellazione dei vecchi totali.');
					}
					
					if (!$in_transaction) {
						\DB::transaction('commit');
					}
					
					$res = true;
				} catch (Exception $e) {
					if ($in_transaction) {
						throw $e;
					} else {
						\DB::transaction('rollback');
					}
					$res = false;
				}
				unset($in_transaction);
			}
			unset($user);
		}
		return $res;
	}
	
	/**
	 * Importazione dei totali dell'ordine
	 * @access public
	 * @param string $file percorso del file CSV
	 * @param array $params parametri vari
	 * @return boolean
	 */
	public function importTotals($file, $params = array()) {
		$res = false;
		if (is_string($file) && trim($file) !== '' && is_file($file)) {
			if (!is_array($params)) {
				$params = array();
			}
			$csv_style = array(
				'delimiter' => isset($params['csv_delimiter']) && is_string($params['csv_delimiter']) && trim($params['csv_delimiter']) != '' ? trim($params['csv_delimiter']) : ';',
				'enclosure' => isset($params['csv_enclosure']) && is_string($params['csv_enclosure']) && trim($params['csv_enclosure']) != '' ? trim($params['csv_delimiter']) : '"', 
			);
			$in_transaction = \DB::transaction('in_transaction');
			if (!$in_transaction) {
				\DB::transaction('begin');
			}
			try {
				$res = true;
				$f = fopen($file, 'r');
				while ($r = fgetcsv($f, 0, $csv_style['delimiter'], $csv_style['enclosure'])) {
					if (is_numeric($r[0]) && $r[0] > 0 && isset($r[2]) && trim($r[2]) !== '') {
						if (!\DB::writerQuery('rep', 'orders_totals', array(
							'order' => $this->get('id'),
							'user' => intval($r[0]),
							'total' => $this->getCsvPrice($r[2])
						))) {
							$res = false;
							throw new Exception('Errore nel salvataggio di un totale.');
						}
					}
					unset($r);
				}
				unset($f, $csv_style);
				if ($res) {
					$this->saveStatusAfterImport(isset($params['status']) ? $params['status'] : null);
				}
				if (!$in_transaction) {
					\DB::transaction('commit');
				}
			} catch (Exception $e) {
				if ($in_transaction) {
					throw $e;
				} else {
					\DB::transaction('rollback');
				}
				$res = false;
			}
			unset($in_transaction);
		}
		return $res;
	}
	
	/**
	 * Salva lo stato dell'ordine dopo un'importazione
	 * @access private
	 * @param string $status stato
	 * @return boolean
	 */
	private function saveStatusAfterImport($status = null) {
		$res = false;
		$in_transaction = \DB::transaction('in_transaction');
		if (!$in_transaction) {
			\DB::transaction('begin');
		}
		try {
			$res = \DB::writerQuery('upd', $this->getTable(), array(
				'status' => is_scalar($status) && trim($status) !== '' ? $status : self::STATUS_DELIVERED
			), array(
				array('field' => 'id', 'value' => $this->get('id'))
			));
			if (!$in_transaction) {
				\DB::transaction('commit');
			}
		} catch (Exception $e) {
			if ($in_transaction) {
				throw $e;
			} else {
				\DB::transaction('rollback');
			}
		}
		return $res;
	}
	
	/**
	 * Importazione dei voti dei prodotti dell'ordine
	 * @access public
	 * @param string $file percorso del file CSV
	 * @param array $params parametri vari
	 * @return boolean
	 */
	public function importVotes($file, $params = array()) {
		$res = false;
		if (is_string($file) && trim($file) != '' && is_file($file)) {
			$csv_style = array(
				'delimiter' => isset($params['csv_delimiter']) && is_string($params['csv_delimiter']) && trim($params['csv_delimiter']) != '' ? trim($params['csv_delimiter']) : ';',
				'enclosure' => isset($params['csv_enclosure']) && is_string($params['csv_enclosure']) && trim($params['csv_enclosure']) != '' ? trim($params['csv_delimiter']) : '"', 
			);
			$f = fopen($file, 'r');
			while ($r = fgetcsv($f, 0, $csv_style['delimiter'], $csv_style['enclosure'])) {
				if (is_numeric($r[0]) && $r[0] > 0 && $this->checkProductExists($r[0])) {
					$data = array(
						'order' => $this->get('id'),
						'product' => $r[0],
						'waste' => isset($r[4]) ? self::getInputQty($r[4]) : 0,
						'vote' => isset($r[7]) && is_numeric($r[7]) && $r[7] >= 1 && $r[7] <= 5 ? (int)$r[7] : null,
						'vote_descr' => isset($r[8]) && is_string($r[8]) ? trim($r[8]) : ''
					);
					if ($data['waste'] > 0 || !is_null($data['vote']) || $data['vote_descr'] != '') {
						if (\DB::writerQuery('rep', 'products_votes', $data)) {
							$res = true;
						}
					}
					unset($data);
				}
				unset($r);
			}
			unset($f, $csv_style);
		}
		return $res;
	}
}