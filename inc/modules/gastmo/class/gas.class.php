<?php
/**
 * Gas Class
 * 
 * Questo file contiene la classe Gas che servono a gestire i GAS
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
 * La classe Gas contiene tutti i metodi per gestire i GAS
 */
class Gas extends \Base {
	const TYPE_GAS = 'G';
	const TYPE_SOTTOGAS = 'S';
	
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $id ID del GAS
	 */
	public function __construct($id = 0) {
		$this->table = 'gas';
		$this->fields = array(
			'id' => array('type' => 'autoincrement', 'key' => 'primary'),
			'title' => array('type' => 'text', 'title' => 'Nome', 'check' => array('string')),
			'parent' => array('type' => 'integer', 'title' => 'Genitore', 'parent' => 'id'),
			'type' => array('type' => 'text', 'title' => 'Tipo', 'check' => array('string'))
		);
		$this->setByParams(array('id' => $id));
		parent::__construct();
	}
	
	/**
	 * Assegna i GAS a un utente
	 * @access public
	 * @param integer $user ID dell'utente
	 * @param array|integer ID dei o del GAS
	 * @return boolean
	 */
	public function setUserGas($user, $gas = array()) {
		$res = false;
		if (is_numeric($user) && $user > 0 && \DB::writerQuery('del', 'users_gas', null, array(array('field' => 'user', 'value' => $user)))) {
			$res = true;
			$gas = is_array($gas) ? array_values($gas) : array($gas);
			$ins = array();
			$counter = count($gas);
			for ($i = 0; $i < $counter; $i++) {
				if (is_numeric($gas[$i]) && $gas[$i] > 0) {
					$ins[] = array(
						'user' => $user,
						'gas' => intval($gas[$i])
					);
				}
			}
			unset($i, $counter);
			if (!empty($ins)) {
				$res = \DB::writerQuery('insmul', 'users_gas', $ins);
			}
			unset($ins);
		}
		return $res;
	}
	
	/**
	 * Restituisce i GAS di un utente
	 * @access public
	 * @param integer $user ID dell'utente
	 * @param string $return tipo di dati da restituire ("id": ID dei GAS, "data": tutti i dati dei GAS; default: "id")
	 * @return array
	 */
	public function getUserGas($user, $return = 'id') {
		$gas = array();
		if (is_numeric($user) && $user > 0) {
			switch (is_string($return) ? $return : null) {
				case 'data':
				
				break;
				case 'id':
				default:
					$gas = \DB::queryCol(array(
						'select' => array('gas'),
						'table' => 'users_gas',
						'where' => array(
							array('field' => 'user', 'value' => $user)
						)
					));
				break;
			}
		}
		return $gas;
	}
}