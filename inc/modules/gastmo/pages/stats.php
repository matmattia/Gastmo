<?php
/**
 * StatsPage Class
 * 
 * Questo file contiene la classe StatsPage che serve a gestire la pagina delle statistiche
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
 * La classe StatsPage contiene tutti i metodi per gestire la pagina delle statistiche
 */
class StatsPage extends \ModulePage {
	/**
	 * Stampa la pagina in base ai parametri
	 * @access public
	 * @return array
	 */
	public function printPage() {
		$title = 'Statistiche';
		$breadcrumb = array(array('url' => '/stats/', 'title' => $title));
		if (\User::checkLogin()) {
			$url = '/stats/';
			$data = $totals = array();
			$data_title = 'Anni';
			$g = new UserGroup();
			$groups = $g->getList(array(
				'select' => array(
					array('value' => '*', 'no_quote' => true),
					array('value' => \DB::quote(0), 'no_quote' => true, 'as' => 'print')
				),
				'order' => array('title' => 'ASC')
			));
			unset($g);
			if (isset($this->params_url[1]) && is_numeric($this->params_url[1]) && $this->params_url[1] > 0) {
				$this->params_url[1] = (int)$this->params_url[1];
				$title .= ' '.$this->params_url[1];
				$url .= $this->params_url[1].'/';
				$breadcrumb[] = array('url' => $url, 'title' => (string)$this->params_url[1]);
				$data_title = 'Mesi';
				for ($month = 1; $month <= 12; $month++) {
					foreach ($groups as $k => $v) {
						$tot = $this->getUserOrderTotal(
							\User::getLoggedUser(),
							$this->getDateOrders($this->params_url[1], $month, $v['id'], \User::getLoggedUser())
						);
						if ($tot > 0) {
							if (!isset($data[$month])) {
								$data[$month] = array(
									'label' => substr(\Lang::getMonth($month, 'ucf'), 0, 3),
									'values' => array()
								);
							}
							$data[$month]['values'][$v['id']] = $tot;
							if (!isset($totals[$month])) {
								$totals[$month] = 0;
							}
							$totals[$month] += $tot;
							$groups[$k]['print'] = true;
						}
						unset($tot, $k, $v);
					}
				}
				unset($month);
			} else {
				$min_date = null;
				$counter = count($groups);
				for ($i = 0; $i < $counter; $i++) {
					$date = $this->getFirstOrderDate($groups[$i]['id'], \User::getLoggedUser());
					if (is_null($date)) {
						unset($groups[$i]);
					} else if (is_null($min_date) || $date < $min_date) {
						$min_date = $date;
					}
					unset($date);
				}
				unset($i, $counter);
				if (!is_null($min_date)) {
					$min_year = date('Y', $min_date);
					for ($year = date('Y'); $year >= $min_year; $year--) {
						foreach ($groups as $k => $v) {
							$tot = $this->getUserOrderTotal(
								\User::getLoggedUser(),
								$this->getDateOrders($year, null, $v['id'], \User::getLoggedUser())
							);
							if ($tot > 0) {
								if (!isset($data[$year])) {
									$data[$year] = array(
										'label' => $year,
										'label_url' => '/stats/'.$year.'/',
										'values' => array()
									);
								}
								$data[$year]['values'][$v['id']] = $tot;
								if (!isset($totals[$year])) {
									$totals[$year] = 0;
								}
								$totals[$year] += $tot;
								$groups[$k]['print'] = true;
							}
							unset($tot, $k, $v);
						}
					}
					unset($min_year, $year);
				}
				unset($min_date);
			}
			
			setBreadcrumb($breadcrumb);
			return array(
				'title' => $title,
				'content' => $this->printTemplate('stats.php', array(
					'title' => $title,
					'groups' => $groups,
					'data_title' => $data_title,
					'data' => $data,
					'totals' => $totals
				)),
				'url' => $url
			);
		} else {
			setBreadcrumb($breadcrumb);
			return array(
				'title' => $title,
				'content' => $this->printTemplate('login.php')
			);
		}
	}
	
	/**
	 * Restituisce la data del primo ordine di un gruppo
	 * @access private
	 * @param integer $group ID del gruppo
	 * @param integer $user eventuale ID dell'utente
	 * @return integer
	 */
	private function getFirstOrderDate($group, $user = null) {
		$date = null;
		if (is_numeric($group) && $group > 0) {
			$prepared = 'stats_get_first_order_date';
			$sql = array(
				'select' => array('orders.shipping_date'),
				'table' => 'orders',
				'join' => array(),
				'where' => array(
					array('field' => 'orders.user_group', 'value' => '?', 'value_type' => 'sql'),
					array('field' => 'orders.shipping_date', 'match' => 'IS NOT'),
					array('field' => 'orders.status', 'value' => Order::STATUS_DELIVERED),
					array('field' => 'orders.online', 'value' => 1)
				),
				'order' => array(
					'orders.shipping_date' => 'ASC'
				),
				'limit' => 1
			);
			$params = array($group);
			if (is_numeric($user) && $user > 0) {
				$prepared .= '_user';
				$sql['join']['carts'] = array('carts.order', 'orders.id');
				$sql['where'][] = array('field' => 'carts.user', 'value' => '?', 'value_type' => 'sql');
				$params[] = $user;
			}
			\DB::prepare($prepared, \DB::createSql($sql));
			unset($sql);
			$res = \DB::queryOne(\DB::execPrepared($prepared, $params));
			unset($prepared, $params);
			if (is_string($res)) {
				$res = strtotime($res);
			}
			if ($res !== false) {
				$date = $res;
			}
			unset($res);
		}
		return $date;
	}
	
	/**
	 * Restituisce gli ordini di un anno o mese
	 * @access private
	 * @param integer $year anno
	 * @param integer $month eventuale mese
	 * @param integer $group eventuale ID del gruppo
	 * @param integer $user eventuale ID dell'utente
	 * @return array
	 */
	private function getDateOrders($year, $month = null, $group = null, $user = null) {
		$orders = array();
		if (is_numeric($year) && $year > 0) {
			$prepared = 'stats_get_date_orders';
			$sql = array(
				'select' => array('orders.id'),
				'table' => 'orders',
				'join' => array(),
				'where' => array(
					array('field' => 'orders.shipping_date', 'match' => '>=', 'value' => '?', 'value_type' => 'sql'),
					array('field' => 'orders.shipping_date', 'match' => '<=', 'value' => '?', 'value_type' => 'sql'),
					array('field' => 'orders.status', 'value' => Order::STATUS_DELIVERED),
					array('field' => 'orders.online', 'value' => 1)
				),
				'group' => array('orders.id'),
				'order' => array(
					'orders.id' => 'ASC'
				)
			);
			if (is_numeric($month) && $month >= 1 && $month <= 12) {
				$date = mktime(0, 0, 0, $month, 1, $year);
				$params = array(
					date('Y-m-d 00:00:00', $date),
					date('Y-m-t 23:59:59', $date)
				);
				unset($date);
			} else {
				$date = mktime(0, 0, 0, 1, 1, $year);
				$params = array(
					date('Y-01-01 00:00:00', $date),
					date('Y-12-31 23:59:59', $date)
				);
				unset($date);
			}
			if (is_numeric($group) && $group > 0) {
				$prepared .= '_group';
				$sql['where'][] = array('field' => 'orders.user_group', 'value' => '?', 'value_type' => 'sql');
				$params[] = $group;
			}
			if (is_numeric($user) && $user > 0) {
				$prepared .= '_user';
				$sql['join']['carts'] = array('carts.order', 'orders.id');
				$sql['where'][] = array('field' => 'carts.user', 'value' => '?', 'value_type' => 'sql');
				$params[] = $user;
			}
			\DB::prepare($prepared, \DB::createSql($sql));
			unset($sql);
			$res = \DB::queryCol(\DB::execPrepared($prepared, $params));
			unset($prepared, $params);
			if (is_array($res)) {
				$orders = $res;
			}
			unset($res);
		}
		return $orders;
	}
	
	/**
	 * Restituisce il totale di un ordine per utente
	 * @access private
	 * @param integer $user ID dell'utente
	 * @param integer|array $orders ID del singolo o degli ordini
	 * @return float
	 */
	private function getUserOrderTotal($user, $orders) {
		$tot = 0.0;
		if (is_numeric($user) && $user > 0) {
			$orders = is_array($orders) ? array_values($orders) : array($orders);
			$counter = count($orders);
			for ($i = 0; $i < $counter; $i++) {
				if (!is_numeric($orders[$i]) || $orders[$i] <= 0) {
					unset($orders[$i]);
				}
			}
			unset($i, $counter);
			if (!empty($orders)) {
				$order = new Order();
				foreach ($orders as $id_order) {
					$tot += $order->getUserTotal($user, $id_order);
					unset($id_order);
				}
				unset($order);
			}
		}
		return $tot;
	}
}