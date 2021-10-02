<?php
/**
 * Category Class
 * 
 * Questo file contiene la classe Category che serve a gestire le categorie
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
 * La classe Category contiene tutti i metodi per gestire le categorie
 */
class Category extends \Base {
	/**
	 * Costruttore della classe
	 * @access public
	 * @param integer $id ID della categoria
	 */
	function __construct($id = 0) {
		$this->table = 'categories';
		$this->fields = array(
			'id' => array('type' => 'autoincrement', 'key' => 'primary'),
			'title' => array('type' => 'text', 'title' => 'Titolo', 'check' => array('string')),
			'order' => array('type' => 'integer', 'title' => 'Ordine', 'check' => array('number')),
			'pos' => array('type' => 'integer', 'title' => 'Posizione', 'check' => array('number'), 'default' => 0)
		);
		$this->setByParams(array('id' => $id));
		parent::__construct();
	}
}