<?php
namespace Gastmo;

$object = '\Gastmo\Product';
$sql = array(
	'left' => array(
		'categories' => array('categories.id', 'products.category'),
		'orders' => array('orders.id', 'categories.order')
	),
	'where' => array(
		array('field' => 'orders.id', 'match' => 'IN', 'value' => Order::getUserManagedOrders(null, 'id'))
	),
	'order' => array(
		'categories.order' => 'DESC',
		'categories.pos' => 'ASC',
		'products.pos' => 'ASC'
	)
);
$operations = array(
	'add' => true,
	'export_list_csv' => true,
	'edit' => true,
	'delete' => true
);
$fields = array(
	array('field' => 'title', 'title' => 'Titolo'),
	array('field' => 'category_title', 'title' => 'Categoria', 'field_sql' => 'categories.title'),
	array('field' => 'order_title', 'title' => 'Ordine', 'field_sql' => 'orders.title')
);