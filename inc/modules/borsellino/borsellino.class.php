<?php
/**
 * Borsellino Class
 * 
 * Questo file contiene la classe Borsellino che serve a gestire il modulo
 * @author Mattia <info@matriz.it>
 * @package MatCMS
 * @link http://www.matriz.it/projects/matcms/ MatCMS
 */

if (!defined('_INCLUDED')) {
	die('Access denied!');
}

/**
 * La classe Borsellino contiene tutti i metodi per gestire il modulo
 */
class Borsellino extends Module {
	/**
	 * Costruttore della classe
	 * @access public
	 */
	public function __construct() {
		parent::__construct();
		
		$this->addHook('gastmo_order_save_cart_begin', array($this, 'checkSaveCartBegin'));
		$this->addHook('gastmo_order_save_cart_end', array($this, 'checkSaveCartEnd'));
	}
	
	/**
	 * @see Module::getInfo()
	 */
	public function getInfo() {
		return array(
			'name' => 'Borsellino'
		);
	}
	
	/**
	 * @see Module::getAdminSections()
	 */
	public function getAdminSections() {
		return array(
			array('url' => 'list_borsellino', 'title' => 'Gestione Borsellino', 'levels' => array('admin', 'subadmin', 'contabile'), 'fontawesome' => 'piggy-bank', 'order' => 'b1'),
			array('url' => 'list_borsellino_users', 'title' => 'Conti borsellino', 'levels' => array('admin', 'contabile'), 'fontawesome' => 'wallet', 'order' => 'b2')
		);
	}
	
	/**
	 * @see Module::getConfigs()
	 */
	public function getConfigs() {
		return array(
			array('name' => 'borsellino_total_block_value', 'title' => 'Valore del totale corrente che blocca gli acquisti', 'type' => 'number', 'attributes' => array('step' => 0.01)),
			array('name' => 'borsellino_expected_total_days', 'title' => 'Numero di giorni per cui calcolare il totale previsto', 'type' => 'number', 'default' => 0),
			array('name' => 'borsellino_expected_total_block_values', 'title' => 'Valori del totale previsto che bloccano gli acquisti', 'type' => 'multi', 'fields' => array(
				array('field' => 'value[{NUM}][value]', 'title' => 'Valore', 'type' => 'number', 'attributes' => array('step' => 0.01), 'field_value' => 'value'),
				array('field' => 'value[{NUM}][days]', 'title' => 'Giorni', 'type' => 'number', 'attributes' => array('min' => 0), 'field_value' => 'days')
			), 'value_type' => 'json')
		);
	}
	
	/**
	 * Verifica iniziale del salvataggio di un carrello di un ordine
	 * @access public
	 * @param boolean|array $check controllo precedente
	 * @param integer $user ID dell'utente
	 * @param integer $order ID dell'ordine
	 * @return array
	 */
	public function checkSaveCartBegin($check, $user, $order) {
		return $this->checkSaveCart($check, $user, $order);
	}
	
	/**
	 * Verifica finale del salvataggio di un carrello di un ordine
	 * @access public
	 * @param boolean|array $check controllo precedente
	 * @param integer $user ID dell'utente
	 * @param integer $order ID dell'ordine
	 * @return array
	 */
	public function checkSaveCartEnd($check, $user, $order) {
		return $this->checkSaveCart($check, $user, $order, true);
	}
	
	/**
	 * Verifica del salvataggio di un carrello di un ordine
	 * @access private
	 * @param boolean|array $check controllo precedente
	 * @param integer $user ID dell'utente
	 * @param integer $order ID dell'ordine
	 * @param boolean $is_end stabilisce se si tratta del controllo finale
	 * @return array
	 */
	private function checkSaveCart($check, $user, $order, $is_end = false) {
		static $begin_totals = array();
		if (is_numeric($user) && $user > 0 && is_numeric($order) && $order > 0) {
			$user = intval($user);
			$order = intval($order);
			$o = new \Gastmo\Order($order);
			$tot = $o->getUserTotal($user);
			unset($o);
			if ($is_end) {
				$msg = null;
				if (is_array($check)) {
					$msg = isset($check['msg']) && is_string($check['msg']) && trim($check['msg']) !== '' ? $check['msg'] : null;
					$check = isset($check['check']) ? $check['check'] : null;
				}
				if ((is_null($check) || $check) && Borsellino\Borsellino::isOrdersBlocked($order, true) && (!isset($begin_totals[$user][$order]) || $begin_totals[$user][$order] <= $tot)) {
					$check = false;
					$msg = 'Non è possibile ordinare a causa del totale non sufficiente presente nel borsellino.';
				}
				$check = array(
					'check' => $check,
					'msg' => $msg
				);
			} else {
				if (!isset($begin_totals[$user])) {
					$begin_totals[$user] = array();
				}
				$begin_totals[$user][$order] = $tot;
			}
			unset($tot);
		}
		return $check;
	}
}