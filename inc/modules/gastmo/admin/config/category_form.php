<?php
namespace Gastmo;

$object = '\Gastmo\Category';
$orders = array('');
$arr = Order::getUserManagedOrders();
$counter = count($arr);
for ($i = 0; $i < $counter; $i++) {
	$orders[] = array(
		'value' => $arr[$i]['id'],
		'label' => $arr[$i]['title']
	);
}
unset($i, $counter, $arr);
$fields = array(
	array('field' => 'title', 'title' => 'Nome'),
	array('field' => 'order', 'title' => 'Ordine', 'type' => 'select', 'value' => $orders),
	array('field' => 'pos', 'title' => 'Posizione')
);
unset($orders);