<?php
namespace Gastmo;

if (isset($_GET['last_year_orders'])) {
	$group = new UserGroup($_GET['last_year_orders']);
	$group->stats('last_year_orders', 'download_csv');
	unset($group);
}

$object = '\Gastmo\UserGroup';
$operations = array(
	'add' => true,
	'last_year_orders' => array('href' => _ADMINH.'index.php?page=list_usergroup&last_year_orders={URLENCODE|ID}', 'title' => 'Ordini dell\'ultimo anno', 'class_icon' => 'usd'),
	'votes' => array('href' => _ADMINH.'index.php?page=list_order&votes={URLENCODE|ID}&type=usergroup', 'title' => 'Voti', 'class_icon' => 'stats'),
	'edit' => true,
	'delete' => true
);
$user = new \User();
$fields = array(
	array('field' => 'title', 'title' => 'Nome'),
	array('field' => 'users', 'title' => 'Referenti', 'field_sql' => '('.\DB::createSQL(array(
		'select' => array(array('value' => 'COUNT(*)', 'no_quote' => true)),
		'table' => 'user_group',
		'where' => array(
			array('field' => 'group', 'value' => 'users_groups.id', 'value_type' => 'field')
		)
	)).')')
	/*array('field' => 'users', 'title' => 'Utenti', 'field_sql' => '('.$user->getSqlAll(array(
		'select' => array(array('value' => 'GROUP_CONCAT('.getUserFullNameSelect('value').' SEPARATOR \', \')', 'no_quote' => true)),
		'join' => array(
			'user_group' => array('user_group.user', 'users.id')
		),
		'where' => array(
			array('field' => 'user_group.group', 'value' => 'users_groups.id', 'value_type' => 'field')
		)
	)).')')*/
);