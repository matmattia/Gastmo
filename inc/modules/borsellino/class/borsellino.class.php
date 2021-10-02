<?php
/**
 * Borsellino Class
 * 
 * Questo file contiene la classe Borsellino che serve a gestire il borsellino
 * @author Mattia <info@matriz.it>
 * @package MatCMS
 * @link http://www.matriz.it/projects/matcms/ MatCMS
 */

namespace Borsellino;

if (!defined('_INCLUDED')) {
	die('Access denied!');
}

/**
 * La classe Borsellino contiene tutti i metodi per gestire il borsellino
 */
class Borsellino extends \Base {
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $id ID del borsellino
	 */
	public function __construct($id = 0) {
		$this->table = 'borsellino';
		$this->fields = array(
			'id' => array('type' => 'autoincrement', 'key' => 'primary'),
			'user' => array('type' => 'integer', 'title' => 'Utente', 'check' => array('number')),
			'date' => array('type' => 'datetime', 'title' => 'Data'),
			'descr' => array('type' => 'text', 'title' => 'Descrizione', 'check' => array('string')),
			'income' => array('type' => 'number', 'title' => 'Entrata'),
			'outflow' => array('type' => 'number', 'title' => 'Uscita')
		);
		$this->setByParams(array('id' => $id));
		parent::__construct();
	}
	
	/**
	 * Stabilisce se gli ordini sono bloccati per l'utente
	 * @access public
	 * @static
	 * @param boolean $reset_cache stabilisce se fare il reset della cache
	 * @return boolean
	 */
	public static function isOrdersBlocked($reset_cache = false) {
		$res = false;
		if (\User::checkLogin()) {
			$tot = \Config::get('borsellino_total_block_value');
			if (is_numeric($tot) && BorsellinoView::getLoggedUserTotal($reset_cache) <= $tot) {
				$res = true;
			} else {
				$values = self::getExpectedTotalBlockValues();
				foreach ($values as $v) {
					if (BorsellinoView::getLoggedUserExpectedTotal(array(
						'days' => $v['days'],
						'reset_cache' => $reset_cache
					)) <= $v['value']) {
						$res = true;
						break;
					}
					unset($v);
				}
				unset($values);
				
			}
			unset($tot);
		}
		return $res;
	}
	
	/**
	 * Restituisce i valori del totale previsto per bloccare gli ordini
	 * @access public
	 * @static
	 * @return array
	 */
	public static function getExpectedTotalBlockValues() {
		$values = array();
		$arr = \Config::get('borsellino_expected_total_block_values');
		if (is_array($arr)) {
			foreach ($arr as $v) {
				if (is_array($v) && isset($v['value']) && is_numeric($v['value'])) {
					$values[] = array(
						'value' => floatval($v['value']),
						'days' => isset($v['days']) && is_numeric($v['days']) && $v['days'] > 0 ? intval($v['days']) : null
					);
				}
				unset($v);
			}
		}
		unset($arr);
		return $values;
	}
}

/**
 * La classe BorsellinoView contiene tutti i metodi per gestire il borsellino come vista
 */
class BorsellinoView extends Borsellino {
	/**
	 * @see Borsellino::__construct()
	 */
	public function __construct($id = 0) {
		parent::__construct();
		$this->table = 'borsellino_view';
		$this->fields['from'] = array('type' => 'text', 'title' => 'Provenienza');
		$this->setByParams(array('id' => $id, 'from' => 'borsellino'));
		$this->checkView();
	}
	
	/**
	 * Restituisce il totale di un utente
	 * @access public
	 * @param integer $user ID dell'utente
	 * @param boolean $reset_cache stabilisce se fare il reset della cache
	 * @return float
	 */
	public function getUserTotal($user, $reset_cache = false) {
		static $cache = array();
		$tot = 0.0;
		if (is_numeric($user) && $user > 0) {
			$user = intval($user);
			if ($reset_cache || !isset($cache[$user])) {
				$borsellino_user_view = new BorsellinoUserView($user);
				$cache[$user] = $borsellino_user_view->exists() ? floatval($borsellino_user_view->get('total')) : $tot;
				unset($borsellino_user_view);
			}
			$tot = $cache[$user];
		}
		return $tot;
	}
	
	/**
	 * Restituisce il totale previsto di un utente
	 * @access public
	 * @param integer $user ID dell'utente
	 * @param array $params parametri vari
	 * @return float
	 */
	public function getUserExpectedTotal($user, $params = array()) {
		static $cache = array();
		$tot = 0.0;
		if (is_numeric($user) && $user > 0) {
			$user = intval($user);
			if (!is_array($params)) {
				$params = array();
			}
			$params['days'] = isset($params['days']) && is_numeric($params['days']) && $params['days'] > 0 ? intval($params['days']) : 0;
			
			$cache_key = md5(json_encode(array(
				'user' => $user,
				'days' => $params['days']
			)));
			
			if ((!isset($params['reset_cache']) || !$params['reset_cache']) && isset($cache[$cache_key])) {
				$tot = $cache[$cache_key];
			} else {
				// Totale degli ordini chiusi
				$tot = $this->getUserTotal($user, isset($params['reset_cache']) ? $params['reset_cache'] : false);
				
				// Totale degli ordini aperti e in consegna
				$sql = array(
					'join' => array(
						'carts' => array('carts.order', 'orders.id')
					),
					'where' => array(
						array('field' => 'orders.status', 'match' => 'IN', 'value' => array(
							\Gastmo\Order::STATUS_OPEN,
							\Gastmo\Order::STATUS_DELIVERING
						)),
						array('field' => 'orders.online', 'value' => 1),
						array('field' => 'carts.user', 'value' => $user)
					),
					'group' => array('orders.id', 'carts.user')
				);
				$days = $params['days'] > 0 ? $params['days'] : \Config::get('borsellino_expected_total_days');
				if (is_numeric($days) && $days > 0) {
					$sql['where'][] = array('field' => 'orders.shipping_date', 'match' => '<=', 'value' => date('Y-m-d 23:59:59', strtotime('+'.intval($days).' days')));
				}
				unset($days);
				$new_sql = \Hook::run('borsellino_gastmo_user_expected_total_orders_sql', array($sql), true);
				if (is_array($new_sql)) {
					$sql = $new_sql;
				}
				unset($new_sql);
				$order = new \Gastmo\Order();
				$orders = $order->getListCol('orders.id', $sql);
				foreach ($orders as $id_order) {
					$tot -= $order->getUserTotal($user, $id_order);
					unset($id_order);
				}
				unset($orders, $order);
				
				$cache[$cache_key] = $tot;
			}
		}
		return $tot;
	}
	
	/**
	 * Restituisce il totale dell'utente collegato
	 * @access public
	 * @static
	 * @param boolean $reset_cache stabilisce se fare il reset della cache
	 * @return float
	 */
	public static function getLoggedUserTotal($reset_cache = false) {
		static $tot = null;
		if ($reset_cache || is_null($tot)) {
			$obj = new BorsellinoView();
			$tot = $obj->getUserTotal(\User::getLoggedUser(), $reset_cache);
		}
		return $tot;
	}
	
	/**
	 * Restituisce il totale previsto dell'utente collegato
	 * @access public
	 * @static
	 * @param array $params parametri vari
	 * @return float
	 */
	public static function getLoggedUserExpectedTotal($params = array()) {
		$obj = new BorsellinoView();
		return $obj->getUserExpectedTotal(\User::getLoggedUser(), $params);
	}
	
	/**
	 * Controlla che la vista esista ed eventualmente la crea
	 * @access private
	 * @param boolean $force stabilisce se forzare la creazione della vista
	 * @return boolean
	 */
	private function checkView($force = false) {
		$res = false;
		//$force = true;
		if (\DB::transaction('in_transaction') || (!$force && \DB::tableExists($this->table))) {
			$res = true;
		} else {
			$selects = array();
			
			// Borsellino
			$borsellino = new Borsellino();
			$selects[] = $borsellino->getSql(array(
				'select' => array(
					array('value' => '*', 'no_quote' => true),
					array('value' => \DB::quote('borsellino'), 'as' => 'from', 'no_quote' => true)
				)
			));
			unset($borsellino);
			
			// Ordini con totali fissi
			$order = new \Gastmo\Order();
			$sql = array(
				'select' => array(
					'orders.id',
					'orders_totals.user',
					array('value' => 'orders.shipping_date', 'as' => 'date'),
					array('value' => 'title', 'as' => 'descr'),
					array('value' => '0', 'as' => 'income', 'no_quote' => true),
					array('value' => 'orders_totals.total', 'as' => 'outflow'),
					array('value' => \DB::quote('order'), 'as' => 'from', 'no_quote' => true)
				),
				'join' => array(
					'orders_totals' => array('orders_totals.order', 'orders.id')
				),
				'where' => array(
					array('field' => 'orders.status', 'value' => \Gastmo\Order::STATUS_DELIVERED),
					array('field' => 'orders.online', 'value' => 1)
				)
			);
			$new_sql = \Hook::run('borsellino_gastmo_order_sql_view', array($sql));
			if (is_array($new_sql)) {
				$sql = $new_sql;
			}
			unset($new_sql);
			$selects[] = $order->getSql($sql);
			unset($sql);
			
			// Ordini con totali in base alla quantità
			$sub_selects = array();
			$sql = array(
				'select' => array(
					'orders.id',
					'carts.user',
					array('value' => 'orders.shipping_date', 'as' => 'date'),
					array('value' => 'title', 'as' => 'descr'),
					array('value' => 'SUM(carts.actual_qty * products.price)', 'as' => 'total', 'no_quote' => true)
				),
				'join' => array(
					'carts' => array('carts.order', 'orders.id'),
					'products' => array('products.id', 'carts.product')
				),
				'where' => array(
					array('field' => 'orders.status', 'value' => \Gastmo\Order::STATUS_DELIVERED),
					array('field' => 'orders.online', 'value' => 1),
					array('field' => 'orders.id', 'match' => 'NOT IN', 'value' => '('.\DB::createSql(array(
						'select' => array(
							'orders_totals.order'
						),
						'table' => 'orders_totals',
						'where' => array(
							array('field' => 'orders_totals.user', 'value' => 'carts.user', 'value_type' => 'field')
						),
						'group' => array('orders_totals.order')
					)).')', 'value_type' => 'sql')
				),
				'group' => array('orders.id', 'carts.user')
			);
			$new_sql = \Hook::run('borsellino_gastmo_order_sql_view', array($sql));
			if (is_array($new_sql)) {
				$sql = $new_sql;
			}
			unset($new_sql);
			$sub_selects[] = $order->getSql($sql);
			unset($sql);
			$sql = array(
				'select' => array(
					'orders.id',
					'carts_extras.user',
					array('value' => 'orders.shipping_date', 'as' => 'date'),
					array('value' => 'title', 'as' => 'descr'),
					array('value' => 'SUM(carts_extras.qty * carts_extras.price)', 'as' => 'total', 'no_quote' => true)
				),
				'join' => array(
					'carts_extras' => array('carts_extras.order', 'orders.id')
				),
				'where' => array(
					array('field' => 'orders.status', 'value' => \Gastmo\Order::STATUS_DELIVERED),
					array('field' => 'orders.online', 'value' => 1),
					array('field' => 'orders.id', 'match' => 'NOT IN', 'value' => '('.\DB::createSql(array(
						'select' => array(
							'orders_totals.order'
						),
						'table' => 'orders_totals',
						'where' => array(
							array('field' => 'orders_totals.user', 'value' => 'carts_extras.user', 'value_type' => 'field')
						),
						'group' => array('orders_totals.order')
					)).')', 'value_type' => 'sql')
				),
				'group' => array('orders.id', 'carts_extras.user')
			);
			$new_sql = \Hook::run('borsellino_gastmo_order_sql_view', array($sql));
			if (is_array($new_sql)) {
				$sql = $new_sql;
			}
			unset($new_sql);
			$sub_selects[] = $order->getSql($sql);
			unset($sql);
			$selects[] = str_replace(\DB::quoteIdentifier('table'), '('.implode(' UNION ', $sub_selects).') AS x', \DB::createSql(array(
				'select' => array(
					'id',
					'user',
					'date',
					'descr',
					array('value' => '0', 'as' => 'income', 'no_quote' => true),
					array('value' => 'SUM(total)', 'as' => 'outflow', 'no_quote' => true),
					array('value' => \DB::quote('order'), 'as' => 'from', 'no_quote' => true)
				),
				'table' => 'table',
				'group' => array(
					'id',
					'user',
					'date',
					'descr'
				)
			)));
			unset($sub_selects);
			
			unset($order);
			
			if (!empty($selects)) {
				$view = \DB::quoteIdentifier($this->table);
				$res = \DB::query('DROP VIEW IF EXISTS '.$view)
					&& \DB::query('CREATE VIEW '.$view.' AS '.implode(' UNION ', $selects));
				unset($view);
			}
			unset($selects);
		}
	}
}

/**
 * La classe BorsellinoUsersView contiene tutti i metodi per gestire il borsellino di ogni utente come vista
 */
class BorsellinoUserView extends \UserView {
	/**
	 * @see Base::__construct()
	 */
	public function __construct($id = 0) {
		parent::__construct();
		$this->table = 'borsellino_users_view';
		$this->fields['total'] = array('type' => 'number', 'title' => 'Totale');
		$this->setByParams(array('id' => $id));
		$this->checkView();
	}
	
	/**
	 * Controlla che la vista esista ed eventualmente la crea
	 * @access private
	 * @param boolean $force stabilisce se forzare la creazione della vista
	 * @return boolean
	 */
	private function checkView($force = false) {
		$res = false;
		if (\DB::transaction('in_transaction') || (!$force && \DB::tableExists($this->table))) {
			$res = true;
		} else {
			$objUser = new \UserView();
			$users_table = $objUser->getTable();
			$view = \DB::quoteIdentifier($this->table);
			$res = \DB::query('DROP VIEW IF EXISTS '.$view)
				&& \DB::query('CREATE VIEW '.$view.' AS '.$objUser->getSql(array(
					'select' => array(
						array('value' => $users_table.'.*', 'no_quote' => true),
						array('value' => 'SUM((CASE WHEN borsellino_view.income IS NULL THEN 0 ELSE borsellino_view.income END) - (CASE WHEN borsellino_view.outflow IS NULL THEN 0 ELSE borsellino_view.outflow END))', 'no_quote' => true, 'as' => 'total')
					),
					'join' => array(
						'borsellino_view' => array('type' => 'left', 'cond' => array('borsellino_view.user', $users_table.'.id'))
					),
					'where' => array(
						'groups' => array(
							array(
								array('field' => $users_table.'.online', 'value' => 1, 'operator' => 'OR'),
								array('field' => 'borsellino_view.income', 'match' => '<>', 'operator' => 'OR'),
								array('field' => 'borsellino_view.outflow', 'match' => '<>', 'operator' => 'OR')
							)
						)
					),
					'group' => array($users_table.'.id')
				)));
			unset($view, $objUser, $users_table);
		}
	}
}