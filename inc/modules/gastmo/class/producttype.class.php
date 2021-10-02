<?php
/**
 * ProductType Class
 * 
 * Questo file contiene la classe ProductType che serve a gestire i tipi di prodotto
 * @author Mattia <info@matriz.it>
 * @package MatCMS\Gastmo
 * @link https://www.matriz.it/projects/matcms/ MatCMS
 * @link https://www.matriz.it/projects/gastmo/ Gastmo
 */

namespace Gastmo;

if (!defined('_INCLUDED')) {
	die('Access denied!');
}

/**
 * La classe ProductType contiene tutti i metodi per gestire i tipi di prodotto
 */
class ProductType extends \Base {
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $id ID del tipo di prodotto
	 */
	public function __construct($id = 0) {
		$this->labels = array(
			'list' => 'Tipi di prodotti',
			'add' => 'Aggiungi tipo di prodotto',
			'edit' => 'Modifica tipo di prodotto'
		);
		$this->table = 'product_types';
		$this->fields = array(
			'id' => array('type' => 'autoincrement', 'key' => 'primary'),
			'title' => array('type' => 'text', 'title' => 'Titolo', 'check' => array('string')),
			'slug' => array('type' => 'text', 'title' => 'Segnaposto', 'check' => array('string')),
			'color' => array('type' => 'text', 'title' => 'Color', 'check' => array('string'))
		);
		$this->setByParams(array('id' => $id));
		parent::__construct();
	}
}