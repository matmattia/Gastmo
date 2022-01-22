<?php
$objUser = new \UserView();
if (isset($_GET['import'])) {
	$no_check_post_form = true;
	if (isset($_FILES['csv']) && isset($_FILES['csv']['error']) && $_FILES['csv']['error'] == UPLOAD_ERR_OK) {
		$rows = $wrong_lis = array();
		$csv = csvToArray($_FILES['csv']['tmp_name'], array(
			'Data Valuta',
			'Causale',
			'Entrate'
		));
		$regexp_causale = array(
			'pattern' => '/RICARICA([\s]+)BORSELLINO([\s]+)([0-9]+)/i',
			'id_position' => 3
		);
		$new_regexp_causale = \Hook::run('borsellino_gastmo_import_regexp', array($regexp_causale), true);
		if (is_array($new_regexp_causale)) {
			$regexp_causale = array_merge($regexp_causale, $new_regexp_causale);
		}
		unset($new_regexp_causale);
		$borsellino = new \Borsellino\Borsellino();
		foreach ($csv as $v) {
			if (is_string($v['Causale']) && trim($v['Causale']) !== '') {
				$income = floatval(str_replace(',', '.', preg_replace('/([^0-9,]+)/', '', $v['Entrate'])));
				$m = array();
				if (preg_match($regexp_causale['pattern'], $v['Causale'], $m)) {
					$d = DateTime::createFromFormat('d/m/Y', $v['Data Valuta']);
					$date = $d === false ? null : $d->format('Y-m-d');
					unset($d);
					$income = floatval(str_replace(',', '.', preg_replace('/([^0-9,]+)/', '', $v['Entrate'])));
					$rows[] = array(
						'sel' => !$date || DB::queryOne($borsellino->getSql(array(
							'select' => array(
								array('value' => 'COUNT(*)', 'no_quote' => true)
							),
							'where' => array(
								array('field' => 'date', 'match' => '>=', 'value' => $date.' 00:00:00'),
								array('field' => 'date', 'match' => '<=', 'value' => $date.' 23:59:59'),
								array('field' => 'user', 'value' => $m[$regexp_causale['id_position']]),
								array('field' => 'income', 'value' => $income)
							),
							'limit' => 1
						))) == 0,
						'date' => $date,
						'descr' => $v['Causale'],
						'user' => $m[$regexp_causale['id_position']],
						'income' => $income
					);
					unset($date);
				} else if ($income > 0) {
					$wrong_lis[] = printHtmlTag('li', html($v['Data Valuta']).' &mdash; '.html($v['Causale']));
				}
				unset($m, $income);
			}
			unset($v);
		}
		unset($csv);
		$fields = array(
			array('field' => 'rows', 'title' => 'Movimenti trovati', 'type' => 'multi', 'fields' => array(
				array('field' => 'borsellino[{NUM}][sel]', 'title' => '', 'type' => 'checkbox', 'field_value' => 'sel'),
				array('field' => 'borsellino[{NUM}][date]', 'title' => 'Data', 'type' => 'date', 'field_value' => 'date'),
				array('field' => 'borsellino[{NUM}][descr]', 'title' => 'Descrizione', 'field_value' => 'descr'),
				array('field' => 'borsellino[{NUM}][user]', 'title' => 'Utente', 'type' => 'select', 'value' => $objUser->getOptions('id', 'alias', array(
					'select' => array(
						array('value' => '*', 'no_quote' => true),
						array('value' => '(CASE WHEN online = 1 THEN \'bg-white text-dark\' ELSE \'bg-danger text-white\' END)', 'no_quote' => true, 'as' => 'class')
					)
				), true, array('class')), 'options_attributes_fields' => array(
					'class' => 'class'
				), 'attributes' => array('data-option-class-to-select' => 1), 'field_value' => 'user'),
				array('field' => 'borsellino[{NUM}][income]', 'title' => 'Entrata', 'type' => 'number', 'attributes' => array('step' => 0.01), 'field_value' => 'income')
			), 'value' => $rows)
		);
		unset($rows);
		if (!empty($wrong_lis)) {
			$fields[] = array('field' => 'wrong_rows', 'title' => 'Movimenti in entrata non ricariche', 'type' => 'custom', 'value' => printHtmlTag('ul', implode('', $wrong_lis)));
		}
		unset($wrong_lis);
	} else {
		if (isset($_POST['borsellino'])) {
			$no_check_post_form = false;
			function insert_borsellino($data) {
				$res = false;
				if (isset($data['borsellino']) && is_array($data['borsellino']) && !empty($data['borsellino'])) {
					DB::transaction('begin');
					try {
						foreach ($data['borsellino'] as $v) {
							if (isset($v['sel']) && $v['sel']) {
								$borsellino = new \Borsellino\Borsellino();
								$c = $borsellino->create(array(
									'date' => isset($v['date']) ? $v['date'] : null,
									'descr' => isset($v['descr']) ? $v['descr'] : null,
									'user' => isset($v['user']) ? $v['user'] : null,
									'income' => isset($v['income']) ? $v['income'] : null
								));
								unset($borsellino);
								if ($c !== true) {
									$res = $c;
									throw new Exception('Errore nel salvataggio.');
								}
							}
						}
						DB::transaction('commit');
						$res = true;
					} catch (Exception $e) {
						DB::transaction('rollback');
					}
				}
				return $res;
			}
		}
		$fields = array(
			array('field' => 'csv', 'title' => 'CSV dei movimenti', 'type' => 'file', 'attributes' => array('accept' => 'text/csv'), 'check' => array('type' => '*.csv')),
			array('field' => 'csv_delimiter', 'title' => 'Separatore campi CSV', 'value' => ';', 'attributes' => array('maxlength' => 1))
		);
	}
} else {
	$object = '\Borsellino\Borsellino';
	$fields = array(
		array('field' => 'user', 'title' => 'Utente', 'type' => 'select', 'value' => $objUser->getOptions('id', 'alias', null, true)),
		array('field' => 'date', 'title' => 'Data', 'type' => 'date', 'default' => date('Y-m-d')),
		array('field' => 'descr', 'title' => 'Descrizione'),
		array('field' => 'income', 'title' => 'Entrata', 'type' => 'number', 'attributes' => array('step' => 0.01)),
		array('field' => 'outflow', 'title' => 'Uscita', 'type' => 'number', 'attributes' => array('step' => 0.01))
	);
}
unset($objUser);