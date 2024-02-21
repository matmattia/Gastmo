<?php
/**
 * Gastmo Class
 * 
 * Questo file contiene la classe Gastmo che serve a gestire il modulo
 * @author Mattia <info@matriz.it>
 * @package MatCMS\Gastmo
 * @link http://www.matriz.it/projects/matcms/ MatCMS
 * @link http://www.matriz.it/projects/gastmo/ Gastmo
 */

if (!defined('_INCLUDED')) {
	die('Access denied!');
}

/**
 * La classe Gastmo contiene tutti i metodi per gestire il modulo
 */
class Gastmo extends Module {
	/**
	 * Costruttore della classe
	 * @access public
	 */
	public function __construct() {
		parent::__construct();
		
		$this->addHook('cron', array($this, 'checkArchivedOrdersTotals'));
	}
	
	/**
	 * @see Module::getInfo()
	 */
	public function getInfo() {
		return array(
			'name' => 'Gastmo'
		);
	}
	
	/**
	 * @see Module::getAdminSections()
	 */
	public function getAdminSections() {
		return array(
			array('url' => 'list_order', 'title' => 'Ordini', 'levels' => array('admin', 'subadmin', 'gestione_ordini', 'magazziniere', 'contabile'), 'fontawesome' => 'shopping-cart', 'order' => 1),
			array('url' => 'list_category', 'title' => 'Categorie Ordini', 'levels' => array('admin', 'subadmin', 'gestione_ordini', 'contabile'), 'fontawesome' => 'tags', 'order' => 2),
			array('url' => 'list_product', 'title' => 'Prodotti Ordini', 'levels' => array('admin', 'subadmin', 'gestione_ordini', 'contabile'), 'fontawesome' => 'carrot', 'order' => 3),
			array('url' => 'list_producttype', 'title' => 'Tipi Prodotto', 'levels' => array('admin', 'subadmin', 'gestione_ordini', 'contabile'), 'fontawesome' => 'info-circle', 'order' => 4),
			array('url' => 'list_usergroup', 'title' => 'Gruppi', 'levels' => array('admin', 'subadmin'), 'fontawesome' => 'people-carry', 'order' => 5),
			array('url' => 'list_stats', 'title' => 'Statistiche', 'levels' => array('admin', 'subadmin', 'contabile'), 'fontawesome' => 'chart-bar', 'order' => 6)
		);
	}
	
	/**
	 * Se necessario, salva i totali degli ordini archiviati
	 * @access public
	 * @return boolean
	 */
	public function checkArchivedOrdersTotals() {
		$order = new \Gastmo\Order();
		return $order->checkAllArchivedTotals();
	}
}