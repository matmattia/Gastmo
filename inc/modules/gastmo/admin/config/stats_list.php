<?php
namespace Gastmo;

$no_print_list = true;

$order = new Order();
if (isset($_GET['year']) && is_numeric($_GET['year']) && $_GET['year'] > 0) {
	$year = intval($_GET['year']);
	if (isset($_GET['group']) && is_numeric($_GET['group']) && $_GET['group'] > 0) {
		$group = new UserGroup($_GET['group']);
		if ($group->exists()) {
			echo printHtmlTag('h1', 'Statistiche '.$year.' - '.html($group->get('title')));
			$objProduct = new Product();
			$products_table = $objProduct->getTable();
			$orders_table = $order->getTable();
			$products = $objProduct->getList(array(
				'select' => array(
					$products_table.'.title',
					$products_table.'.um',
					array('value' => 'SUM(carts.actual_qty)', 'no_quote' => true, 'as' => 'qty'),
					array('value' => 'SUM(carts.actual_qty * '.$products_table.'.price)', 'no_quote' => true, 'as' => 'total'),
					array('value' => 'COUNT(DISTINCT carts.user)', 'no_quote' => true, 'as' => 'users')
				),
				'join' => array(
					'carts' => array('carts.product', $products_table.'.id'),
					$orders_table => array($orders_table.'.id', 'carts.order')
				),
				'where' => array(
					array('field' => $orders_table.'.user_group', 'value' => $group->get('id')),
					array('field' => $orders_table.'.shipping_date', 'match' => '>=', 'value' => $year.'-01-01 00:00:00'),
					array('field' => $orders_table.'.shipping_date', 'match' => '<=', 'value' => $year.'-12-31 23:59:59'),
					array('field' => $orders_table.'.status', 'value' => Order::STATUS_DELIVERED),
					array('field' => $orders_table.'.online', 'value' => 1),
					array('field' => 'carts.actual_qty', 'match' => 'IS NOT'),
					array('field' => 'carts.actual_qty', 'match' => '>', 'value' => 0)
				),
				'group' => array(
					$products_table.'.title',
					$products_table.'.um'
				),
				'order' => array(
					$products_table.'.title' => 'ASC'
				)
			));
			if (empty($products)) {
				echo \Admin::printMessage('Nessun dato adatto per le statistiche.', 'info');
			} else {
				$trs = '';
				foreach ($products as $product) {
					$trs .= printHtmlTag(
						'tr',
						printHtmlTag('th', html($product['title']), array('scope' => 'row'))
						.printHtmlTag('td', number_format(floatval($product['qty']), 0, ',', '.').' '.html($product['um']))
						.printHtmlTag('td', printMoney($product['total']))
						.printHtmlTag('td', $product['users'])
					);
				}
				echo printHtmlTag(
					'table',
					printHtmlTag('thead', printHtmlTag(
						'tr',
						printHtmlTag('th', 'Prodotto')
						.printHtmlTag('th', 'Quantit&agrave;')
						.printHtmlTag('th', 'Totale')
						.printHtmlTag('th', 'Persone')
					))
					.printHtmlTag('tbody', $trs),
					array('class' => 'table')
				);
			}
		} else {
			echo printHtmlTag('h1', 'Statistiche '.$year).\Admin::printMessage('Questo gruppo non esiste.', 'warning');
		}
	} else {
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
					printHtmlTag('th', printHtmlTag('a', html($groups[$i]['title']), array('href' => _ADMINH.'index.php?page=list_stats&year='.$year.'&group='.intval($groups[$i]['id']))), array('scope' => 'row'))
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
	}
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