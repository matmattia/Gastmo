<?php
/**
 * BorsellinoUserArchivedOrdersTotals Class
 * 
 * Questo file contiene la classe BorsellinoUserArchivedOrdersTotals che serve a gestire il totale degli ordini archiviati per ogni utente
 * @author Mattia <info@matriz.it>
 * @package MatCMS
 * @link http://www.matriz.it/projects/matcms/ MatCMS
 */

namespace Borsellino;

if (!defined('_INCLUDED')) {
	die('Access denied!');
}

/**
 * La classe BorsellinoUserArchivedOrdersTotals contiene tutti i metodi per gestire il totale degli ordini archiviati per ogni utente
 */
class BorsellinoUserArchivedOrdersTotals extends \Base {
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $user ID dell'utente
	 */
	public function __construct($user = 0) {
		$this->table = 'borsellino_user_archived_orders_totals';
		$this->fields = array(
			'user' => array('type' => 'integer', 'key' => 'primary', 'title' => 'Utente', 'check' => array('number')),
			'total' => array('type' => 'number', 'title' => 'Total')
		);
		$this->setByParams(array('user' => $user));
		parent::__construct();
	}
	
	/**
	 * Inserisce il totale di un utente, se non è presente
	 * @access public
	 * @param integer $user ID dell'utente
	 * @param boolean $force_update stabilisce se forzare l'aggiornamento del totale
	 * @return boolean
	 */
	public function checkUser($user, $force_update = false) {
		$res = false;
		if (is_numeric($user) && $user > 0) {
			$total = $force_update ? null : $this->getOneData($user, 'total');
			if (is_numeric($total) && $total >= 0) {
				$res = true;
			} else {
				$total = 0;
				$objOrder = new \Gastmo\Order();
				$sql = array(
					'where' => array(
						array('field' => 'status', 'value' => \Gastmo\Order::STATUS_DELIVERED),
						array('field' => 'archived', 'value' => 1),
						array('field' => 'online', 'value' => 1)
					)
				);
				$new_sql = \Hook::run('borsellino_gastmo_order_sql_view', array($sql));
				if (is_array($new_sql)) {
					$sql = $new_sql;
				}
				unset($new_sql);
				$orders = $objOrder->getListCol('id', $sql);
				foreach ($orders as $v) {
					$total += $objOrder->getUserTotal($user, $v);
				}
				$res = \DB::writerQuery('rep', $this->getTable(), array(
					'user' => $user,
					'total' => $total
				));
			}
			unset($total);
		}
		return $res;
	}
	
	/**
	 * Inserisce i totali degli utenti, se non sono presenti
	 * @access public
	 * @param array $users utenti da verificare (se non specificati, vengono controllati tutti)
	 * @param boolean $force_update stabilisce se forzare l'aggiornamento del totale
	 * @return boolean
	 */
	public function checkUsers($users = null, $force_update = false) {
		$res = true;
		if (!is_array($users)) {
			$objUser = new \User();
			$users = $objUser->getListCol('id');
			unset($objUser);
		}
		foreach ($users as $v) {
			if (!$this->checkUser($v, $force_update)) {
				$res = false;
			}
			unset($v);
		}
		unset($arr);
		return $res;
	}
}