<?php
namespace Gastmo;

$object = '\Gastmo\UserGroup';
$users = array();
$u = new \User();
$list = $u->getListAll(array('where' => array(
	array('field' => 'level', 'match' => 'IN', 'value' => array('gestione_ordini', 'magazziniere'))
)));
unset($u);
$counter = count($list);
for ($i = 0; $i < $counter; $i++) {
	$users[] = array(
		'value' => $list[$i]['id'],
		'label' => getUserFullName($list[$i])
	);
}
unset($i, $counter, $list);
$fields = array(
	array('field' => 'title', 'title' => 'Nome', 'required' => true),
	array_merge(
		array('field' => 'users', 'title' => 'Utenti', 'type' => 'multicheckbox', 'value' => multiSort($users, 'label')),
		$edit ? array('selected' => isset($_GET['id']) ? UserGroup::getUsers($_GET['id'], false) : array()) : array()
	)
);
unset($users);