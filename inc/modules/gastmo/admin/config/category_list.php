<?php
namespace Gastmo;

$object = '\Gastmo\Category';
$sql = array(
	'left' => array(
		'orders' => array('orders.id', 'categories.order')
	),
	'where' => array(
		array('field' => 'orders.id', 'match' => 'IN', 'value' => Order::getUserManagedOrders(null, 'id'))
	),
	'order' => array(
		'categories.order' => 'DESC',
		'categories.pos' => 'ASC'
	)
);
$operations = array(
	'add' => true,
	'edit' => true,
	'delete' => true
);
$fields = array(
	array('field' => 'title', 'title' => 'Titolo'),
	array('field' => 'order_title', 'title' => 'Ordine', 'field_sql' => 'orders.title')
);