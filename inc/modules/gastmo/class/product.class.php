<?php
/**
 * Product Class
 * 
 * Questo file contiene la classe Product che serve a gestire i prodotti
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
 * La classe Product contiene tutti i metodi per gestire i prodotti
 */
class Product extends \Base {
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $id ID del prodotto
	 */
	public function __construct($id = 0) {
		$this->table = 'products';
		$this->fields = array(
			'id' => array('type' => 'autoincrement', 'key' => 'primary'),
			'title' => array('type' => 'text', 'title' => 'Titolo', 'check' => array('string')),
			'category' => array('type' => 'integer', 'titolo' => 'Categoria', 'check' => array('number')),
			'qty_package' => array('type' => 'float', 'titolo' => 'Quantità Collo', 'check' => array('number')),
			'um' => array('type' => 'text', 'title' => 'UM', 'default' => ''),
			'price' => array('type' => 'float', 'titolo' => 'Prezzo', 'check' => array('number')),
			'maker' => array('type' => 'text', 'title' => 'Produttore', 'default' => ''),
			'location' => array('type' => 'text', 'title' => 'Provincia', 'default' => ''),
			'vat' => array('type' => 'float', 'titolo' => 'IVA', 'check' => array('number')),
			'package_price' => array('type' => 'float', 'titolo' => 'Prezzo', 'check' => array('number')),
			'note' => array('type' => 'text', 'title' => 'Note', 'default' => ''),
			'type' => array('type' => 'integer', 'titolo' => 'Tipo', 'check' => array('number'), 'default' => 0),
			'pos' => array('type' => 'integer', 'title' => 'Posizione', 'default' => 0)
		);
		$this->setByParams(array('id' => $id));
		parent::__construct();
	}
	
	/**
	 * Restituisce la quantità ordinata di un prodotto
	 * @access public
	 * @static
	 * @param integer $pid ID del prodotto
	 * @param integer $user ID dell'utente
	 * @return float
	 */
	public static function getOrdered($pid, $user = 0) {
		$q = 0;
		if (is_numeric($pid) && $pid > 0) {
			$prepared = 'product_getordered';
			$sql = 'SELECT SUM(qty) FROM carts WHERE product = ?';
			$params = array(intval($pid));
			if (is_numeric($user) && $user > 0) {
				$prepared .= '_user';
				$sql .= ' AND user = ? LIMIT 1';
				$params[] = (int)$user;
			}
			if (\DB::prepare($prepared, $sql)) {
				$q = \DB::queryOne(\DB::execPrepared($prepared, $params));
				$q = is_numeric($q) && $q > 0 ? (float)$q : 0;
			}
			unset($prepared, $sql, $params);
		}
		return $q;
	}
}