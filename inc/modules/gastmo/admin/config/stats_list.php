<?php
namespace Gastmo;

$no_print_list = true;

$order = new Order();
if (isset($_GET['year']) && is_numeric($_GET['year']) && $_GET['year'] > 0) {
	$year = intval($_GET['year']);
	$trs = '';
	
	$g = new UserGroup();
	$groups = $g->getList(array(
		'order' => array('title' => 'ASC')
	));
	unset($g);
	$counter = count($groups);
	for ($i = 0; $i < $counter; $i++) {
		$orders = $order->getList(array(
			'where' => array(
				array('field' => 'user_group', 'value' => $groups[$i]['id']),
				array('field' => 'shipping_date', 'match' => '>=', 'value' => $year.'-01-01 00:00:00'),
				array('field' => 'shipping_date', 'match' => '<=', 'value' => $year.'-12-31 23:59:59'),
				array('field' => 'status', 'value' => Order::STATUS_DELIVERED),
				array('field' => 'online', 'value' => 1)
			)
		));
		$counter2 = count($orders);
		if ($counter2 > 0) {
			$totals = array(
				'total' => 0,
				'orders' => $counter2,
				'users' => array()
			);
			for ($j = 0; $j < $counter2; $j++) {
				$users = Order::getUsers($orders[$j]['id']);
				foreach ($users as $user) {
					$totals['total'] += $order->getUserTotal($user, $orders[$j]['id']);
					if (!isset($totals['users'][$user])) {
						$totals['users'][$user] = 0;
					}
					$totals['users'][$user]++;
					unset($user);
				}
				unset($users);
			}
			unset($j);
			$trs .= printHtmlTag(
				'tr',
				printHtmlTag('th', html($groups[$i]['title']), array('scope' => 'row'))
				.printHtmlTag('td', printMoney($totals['total']))
				.printHtmlTag('td', $totals['orders'])
				.printHtmlTag('td', count($totals['users']))
				.printHtmlTag('td', count(array_filter($totals['users'], function($users) {
					return $users >= 10;
				})))
			);
			unset($totals);
		}
		unset($counter2, $orders);
	}
	unset($i, $counter, $groups);
	
	echo printHtmlTag('h1', 'Statistiche '.$year);
	if ($trs === '') {
		echo \Admin::printMessage('Nessun dato adatto per le statistiche del '.$year.'.', 'info');
	} else {
		echo printHtmlTag(
			'table',
			printHtmlTag('thead', printHtmlTag(
				'tr',
				printHtmlTag('th', '')
				.printHtmlTag('th', 'Totale')
				.printHtmlTag('th', 'Ordini')
				.printHtmlTag('th', 'Persone')
				.printHtmlTag('th', 'Persone (&ge;10)')
			))
			.printHtmlTag('tbody', $trs),
			array('class' => 'table')
		);
	}
	unset($trs, $year);
} else {
	echo printHtmlTag('h1', 'Statistiche');
	$years = $order->getList(array(
		'select' => array(
			array('value' => 'YEAR(shipping_date)', 'no_quote' => true, 'as' => 'year')
		),
		'where' => array(
			array('field' => 'shipping_date', 'match' => '<>')
		),
		'group' => array('year')
	));
	if (empty($years)) {
		echo \Admin::printMessage('Nessun dato adatto per le statistiche.', 'info');
	} else {
		$lis = '';
		foreach ($years as $year) {
			$lis .= printHtmlTag('li', printHtmlTag('a', html($year['year']), array('href' => _ADMINH.'index.php?page=list_stats&year='.$year['year'])));
			unset($year);
		}
		echo printHtmlTag('ul', $lis);
		unset($lis);
	}
	unset($years);
}
unset($order);