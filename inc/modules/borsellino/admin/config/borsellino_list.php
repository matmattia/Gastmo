<?php
$object = '\Borsellino\Borsellino';

$sql = array(
	'join' => array(
		'users_view' => array('type' => 'left', 'cond' => array('users_view.id', 'borsellino.user'))
	),
	'order' => array(
		'borsellino.date' => 'DESC',
		'borsellino.id' => 'DESC'
	)
);
$operations = array(
	'add' => true,
	'edit' => true,
	'delete' => true,
	'export_list_csv' => true,
	'import' => array(
		'href' => _ADMINH.'index.php?page=create_borsellino&import=1',
		'title' => 'Importa movimenti',
		'class_icon' => 'file-import',
		'list_operation' => true
	)
);
$fields = array(
	array('field' => 'date', 'title' => 'Data'),
	array('field' => 'user', 'title' => 'Utente', 'field_sql' => 'users_view.alias'),
	array('field' => 'descr', 'title' => 'Descrizione'),
	array('field' => 'income', 'title' => 'Entrata'),
	array('field' => 'outflow', 'title' => 'Uscita')
);