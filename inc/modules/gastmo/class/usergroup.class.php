<?php
/**
 * UserGroup Class
 * 
 * Questo file contiene la classe UserGroup che serve a gestire i gruppi degli prodotto
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
 * La classe UserGroup contiene tutti i metodi per gestire i gruppi degli prodotto
 */
class UserGroup extends \Base {
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $id ID del gruppo
	 */
	public function __construct($id = 0) {
		$this->table = 'users_groups';
		$this->fields = array(
			'id' => array('type' => 'autoincrement', 'key' => 'primary'),
			'title' => array('type' => 'text', 'title' => 'Titolo', 'check' => array('string'))
		);
		$this->setByParams(array('id' => $id));
		parent::__construct();
	}
	
	/**
	 * Dopo il salvataggio dei dati, salva gli utenti assegnati al gruppo
	 * @access protected
	 * @param boolean|array risultato del salvataggio
	 * @param array $data dati inviati
	 * @param array $files file inviati
	 * @param boolean $edit stabilisce se si tratta di una modifica
	 * @return boolean|array
	 */
	protected function afterSaveData($check, $data, $files = array(), $edit = false) {
		if ($check === true) {
			$id = (int)$this->get('id');
			if (\DB::writerQuery('del', 'user_group', null, array(array('field' => 'group', 'value' => $id)))) {
				if (isset($data['users']) && is_array($data['users']) && !empty($data['users'])) {
					$values = array();
					foreach ($data['users'] as $v) {
						if (is_numeric($v) && $v > 0) {
							$values[] = array('user' => (int)$v, 'group' => $id);
						}
						unset($v);
					}
					if (!empty($values) && !\DB::writerQuery('insmul', 'user_group', $values)) {
						$check = array(\Lang::get('err_dbwrite'));
					}
					unset($values);
				}
			} else {
				$check = array(\Lang::get('err_dbwrite'));
			}
		}
		return $check;
	}
	
	/**
	 * Restituisce gli utenti di un gruppo
	 * @access public
	 * @static
	 * @param integer $gid ID del gruppo
	 * @param boolean $return_all_data stabilisce se restituire tutti i dati degli utenti
	 * @return array
	 */
	public static function getUsers($gid, $return_all_data = true) {
		$users = array();
		if (is_numeric($gid) && $gid > 0) {
			$sql = array(
				'join' => array('user_group' => array('cond' => array('user_group.user', 'users.id'))),
				'where' => array(array('field' => 'user_group.group', 'value' => $gid))
			);
			$u = new \User();
			$users = $return_all_data ? $u->getList($sql) : \DB::queryCol($u->getSql(array_merge($sql, array('select' => 'users.id'))));
			unset($u, $sql);
		}
		return $users;
	}
	
	/**
	 * Restituisce i gruppi di un utente
	 * @access public
	 * @static
	 * @param integer $uid ID dell'utente
	 * @param string $field singolo campo da restituire
	 * @return array
	 */
	public static function getUserGroups($uid, $field = '') {
		$groups = array();
		if (is_numeric($uid) && $uid > 0) {
			$g = new UserGroup();
			$groups = $g->getList(array(
				'join' => array('user_group' => array('cond' => array('user_group.group', 'users_groups.id'))),
				'where' => array(array('field' => 'user_group.user', 'value' => $uid))
			));
			unset($g);
			if (is_string($field) && trim($field) != '') {
				$arr = $groups;
				$groups = array();
				$counter = count($arr);
				for ($i = 0; $i < $counter; $i++) {
					if (isset($arr[$i][$field])) {
						$groups[] = $arr[$i][$field];
					}
				}
				unset($i, $counter, $arr);
			}
		}
		return $groups;
	}
	
	/**
	 * Statistiche sul gruppo
	 * @access public
	 * @param string $type tipo di statistica
	 * @param string $return formato del dato restituito
	 * @return mixed
	 */
	public function stats($type, $return = null) {
		switch (is_string($type) ? $type : null) {
			case 'last_year_orders':
				$o = new Order();
				$orders = $o->getList(array('where' => array(
					array('field' => 'user_group', 'value' => $this->get('id')),
					array('field' => 'shipping_date', 'match' => '<=', 'value' => date('Y-m-d 23:59:59')),
					array('field' => 'shipping_date', 'match' => '>=', 'value' => date('Y-m-d 00:00:00', strtotime('-1 year')))
				)));
				unset($o);
				$counter = count($orders);
				if ($counter > 0) {
					\DB::prepare('get_order_actual_qty', \DB::createSql(array(
						'table' => 'carts',
						'where' => array(
							array('field' => 'order', 'value' => '?', 'value_type' => 'sql'),
							array('field' => 'actual_qty', 'match' => 'IS NOT')
						)
					)));
					$users = array();
					for ($i = 0; $i < $counter; $i++) {
						$carts = \DB::queryRows(\DB::execPrepared('get_order_actual_qty', array($orders[$i]['id'])));
						$counter2 = count($carts);
						if ($counter2 > 0) {
							for ($j = 0; $j < $counter2; $j++) {
								$carts[$j]['user'] = (int)$carts[$j]['user'];
								if (!isset($users[$carts[$j]['user']])) {
									$users[$carts[$j]['user']] = array();
								}
								$carts[$j]['product'] = (int)$carts[$j]['product'];
								if (!isset($carts[$j]['user'][$carts[$j]['product']])) {
									$users[$carts[$j]['user']][$carts[$j]['product']] = 0;
								}
								$users[$carts[$j]['user']][$carts[$j]['product']] += floatval($carts[$j]['actual_qty']);
							}
							unset($j);
						} else {
							$oe = new OrderExporter($orders[$i]['id']);
							$json = @json_decode($oe->getCurrentExport('json'), true);
							unset($oe);
							if (is_array($json) && isset($json['qty']) && is_array($json['qty']) && !empty($json['qty'])) {
								foreach ($json['qty'] as $id_user => $qty) {
									if (is_numeric($id_user) && $id_user > 0 && is_array($qty) && !empty($qty)) {
										$id_user = (int)$id_user;
										if (!isset($users[$id_user])) {
											$users[$id_user] = array();
										}
										foreach ($qty as $id_product => $q) {
											if (is_numeric($id_product) && $id_product > 0 && is_numeric($q) && $q > 0) {
												$id_product = (int)$id_product;
												if (!isset($users[$id_user][$id_product])) {
													$users[$id_user][$id_product] = 0;
												}
												$users[$id_user][$id_product] += floatval($q);
											}
											unset($id_product, $q);
										}
									}
									unset($id_user, $qty);
								}
							}
							unset($json);
						}
						unset($counter2, $carts);
					}
					unset($i);
				}
				unset($counter, $orders);
				$res = array();
				$products_prices = array();
				foreach ($users as $id_user => $products) {
					$tot = 0;
					foreach ($products as $id_product => $qty) {
						if (!isset($products_prices[$id_product])) {
							$p = new Product($id_product);
							$products_prices[$id_product] = floatval($p->get('price'));
							unset($p);
						}
						$tot += $products_prices[$id_product] * $qty;
						unset($id_product, $qty);
					}
					if ($tot > 0) {
						$u = new \User($id_user);
						if ($u->exists()) {
							$res[] = array(
								'Username' => $u->get('username'),
								'Cognome' => $u->get('surname'),
								'Nome' => $u->get('name'),
								'E-mail' => $u->get('email'),
								'Totale' => number_format($tot, 2, ',', '.')
							);
						}
						unset($u);
					}
					unset($id_user, $products);
				}
				unset($products_prices);
			break;
			default:
				$res = null;
			break;
		}
		switch (is_string($return) ? $return : null) {
			case 'csv':
				$res = arrayToCsv($res);
			break;
			case 'download_csv':
				$csv = arrayToCsv($res);
				header('Content-Type: text/csv');
				header('Content-Disposition: attachment; filename="stats.csv"');
				header('Content-Transfer-Encoding: binary');
				header('Expires: 0');
				header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
				header('Pragma: public');
				header('Content-Length: '.strlen($csv));
				echo $csv;
				unset($csv);
				exit();
			break;
		}
		return $res;
	}
}