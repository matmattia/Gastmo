<?php
/**
 * BorsellinoPage Class
 * 
 * Questo file contiene la classe BorsellinoPage che serve a gestire la pagina del borsellino
 * @author Mattia <info@matriz.it>
 * @package MatCMS\Gastmo
 * @link http://www.matriz.it/projects/matcms/ MatCMS
 */

namespace Borsellino;

if (!defined('_INCLUDED')) {
	die('Access denied!');
}

/**
 * La classe BorsellinoPage contiene tutti i metodi per gestire la pagina del borsellino
 */
class BorsellinoPage extends \ModulePage {
	/**
	 * Stampa la pagina in base ai parametri
	 * @access public
	 * @return array
	 */
	public function printPage() {
		$title = 'Borsellino';
		$breadcrumb = array(array('url' => '/borsellino/', 'title' => $title));
		if (\User::checkLogin()) {
			$url = '/borsellino/';
			$page = isset($this->params_url[1]) && is_numeric($this->params_url[1]) && $this->params_url[1] > 1 ? intval($this->params_url[1]) : 1;
			
			$borsellinoView = new BorsellinoView();
			$sql = array(
				'where' => array(
					array('field' => 'user', 'value' => \User::getLoggedUser())
				)
			);
			$counter = $borsellinoView->countList($sql);
			$perpage = 10;
			$pages = ceil($counter / $perpage);
			if ($page > $pages) {
				$page = $pages;
			}
			$list = $borsellinoView->getList(array_merge($sql, array(
				'order' => array(
					'date' => 'DESC'
				),
				'limit' => array(
					'offset' => ($page - 1) * $perpage,
					'limit' => $perpage
				)
			)));
			unset($sql, $borsellinoView);
			
			$totals = array(
				array(
					'title' => 'Totale corrente',
					'value' => BorsellinoView::getLoggedUserTotal()
				),
				array(
					'title' => 'Totale previsto',
					'value' => BorsellinoView::getLoggedUserExpectedTotal()
				)
			);
			$block_values = Borsellino::getExpectedTotalBlockValues();
			foreach ($block_values as $v) {
				if ($v['days'] > 0) {
					$totals[] = array(
						'title' => 'Tot. previsto a '.$v['days'].' giorni',
						'value' => BorsellinoView::getLoggedUserExpectedTotal(array(
							'days' => $v['days']
						))
					);
				}
				unset($v);
			}
			unset($block_values);
			
			$content = $this->printTemplate('borsellino.php', array(
				'title' => $title,
				'totals' => $totals,
				'list' => $list,
				'pages' => $pages,
				'page' => $page
			));
			unset($totals, $list, $pages);
			$new_content = \Hook::run('borsellino_gastmo_print_html_content', array($content));
			if (is_string($new_content)) {
				$content = $new_content;
			}
			unset($new_content);
			
			if ($page > 1) {
				$url .= $page.'/';
			}
			unset($page);
			setBreadcrumb($breadcrumb);
			return array(
				'title' => $title,
				'content' => $content,
				'url' => $url
			);
		} else {
			setBreadcrumb($breadcrumb);
			return array(
				'title' => $title,
				'content' => $this->printTemplate('login.php')
			);
		}
	}
}