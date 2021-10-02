<?php
/**
 * Restituisce il nome completo di un utente
 * @param integer|User|array ID, oggetto o dati di un utente
 * @return string
 */
function getUserFullName($user) {
	$name = null;
	$user = User::getDataArray($user);
	if ($user) {
		$name = '';
		if (isset($user['name']) && is_scalar($user['name'])) {
			$name = trim($user['name']);
		}
		if (isset($user['surname']) && is_scalar($user['surname'])) {
			$name .= ' '.trim($user['surname']);
		}
		$name = trim($name);
		if ($name == '' && isset($user['username']) && is_scalar($user['username'])) {
			$name = trim($user['username']);
		}
	}
	return $name;
}

/**
 * Restituisce il parametro da aggiungere alla query per estrarre il nome completo di un utente
 * @param string $return tipo di dato da restituire
 * @return mixed
 */
function getUserFullNameSelect($return = null) {
	$select = array(
		'value' => 'CONCAT('.\DB::quoteIdentifier('users_data.name').', \' \', '.\DB::quoteIdentifier('users_data.surname').')',
		'no_quote' => true
	);
	switch (is_string($return) ? $return : null) {
		case 'full':
			$select = array($select);
		break;
		case 'value':
			$select = $select['value'];
		break;
	}
	return $select;
}