<?php

/**
 * This file is part of the Nextras community extensions of Nette Framework
 *
 * @license    MIT
 * @link       https://github.com/nextras
 * @author     Jan Skrasek
 */

namespace ADT\Datagrid;

use Nette\Application\UI;
use Nette\Templating\IFileTemplate;
use Nette\Utils\Html;
use Nette\Utils\Paginator;
use Nette\Utils\Callback;
use Nette\Localization\ITranslator;



class Datagrid extends UI\Control
{
	/** @var string */
	const ORDER_ASC = 'asc';

	/** @var string */
	const ORDER_DESC = 'desc';

	/**
	 * Tři stavy pro řazení. (Asc, Desc, neřadit)
	 * @var int
	 */
	const ORDER_STATE_COUNT_THREE = 1;

	/**
	 * Dva stavy pro řazení. (Asc, Desc)
	 * @var int
	 */
	const ORDER_STATE_COUNT_TWO = 2;

	/** @persistent */
	public $filter = array();

	/** @persistent */
	public $orderColumn;

	/** @persistent */
	public $orderType = self::ORDER_ASC;

	/** @persistent */
	public $page = 1;

	/**
	 * V jakém režimu má fungovat řazení?
	 * @see Datagrid::ORDER_MODE_TWOWAY
	 * @see Datagrid::ORDER_MODE_THREEWAY
	 * @var int
	 */
	protected $orderStateCount = self::ORDER_STATE_COUNT_THREE;

	/**
	 * Má se filtr vykreslovat jako oddělený formulář? Pokud ano, tak pro
	 * vykreslování filtru používáme {control myGridControl:filter} a pro grid
	 * {control myGridControl}. Pokud ne, tak pro celý grid {control myGridControl}.
	 * @var boolean
	 */
	public $separateFilter = FALSE;

	/**
	 * Mají se hodnoty z filtru ukládat do session?
	 * @var boolean
	 */
	public $persistentFilter = FALSE;
	
	private $templateFile = __DIR__ . '/Datagrid.latte';

	const SESSION_SECTION = 'adt/datagrid';

	protected function getSessionSectionName() {
		return static::SESSION_SECTION .'/'. $this->getReflection()->getName();
	}

	protected function getSession() {
		return $this->presenter->getSession($this->getSessionSectionName());
	}

	protected $isFormCreated = FALSE;

	/** @var array */
	protected $filterDataSource = array();

	/** @var array */
	protected $columns = array();

	/** @var callable */
	protected $columnGetterCallback;

	/** @var callable */
	protected $dataSourceCallback;

	/** @var mixed */
	protected $editFormFactory;

	/** @var mixed */
	protected $editFormCallback;

	/** @var callable */
	protected $filterFormFactory;

	/** @var array */
	protected $filterDefaults = array();

	/** @var Paginator */
	protected $paginator;

	/** @var ITranslator */
	protected $translator;

	/** @var callable */
	protected $paginatorItemsCountCallback;

	/** @var mixed */
	protected $editRowKey;

	/** @var string */
	protected $rowPrimaryKey;

	/** @var mixed */
	protected $data;

	/** @var array */
	protected $cellsTemplates = array();

	/** @var boolean */
	protected $redrawOnlyRows = FALSE;

	/**
	 * Ma se zobrazovat sloupec se radkovymi akcemi?
	 * @var bool
	 */
	protected $showActionsColumn = TRUE;

	public $showPaginator = TRUE;




	/**
	 * Nastaví režim řazení.
	 * @see Datagrid::ORDER_STATE_COUNT_TWO
	 * @see Datagrid::ORDER_STATE_COUNT_THREE
	 * @param int $orderStateCount
	 * @return $this
	 */
	public function setOrderStateCount($orderStateCount) {
		$this->orderStateCount = $orderStateCount;
		return $this;
	}


	/**
	 * Vrátí aktuální režim řazení.
	 * @see Datagrid::ORDER_STATE_COUNT_TWO
	 * @see Datagrid::ORDER_STATE_COUNT_THREE
	 * @return int
	 */
	public function getOrderStateCount() {
		return $this->orderStateCount;
	}



	/**
	 * Adds column
	 * @param  string
	 * @param  string
	 * @return Column
	 */
	public function addColumn($name, $label = NULL)
	{
		if (!$this->rowPrimaryKey) {
			$this->rowPrimaryKey = $name;
		}

		$label = $label ? $this->translate($label) : ucfirst($name);
		return $this->columns[] = new Column($name, $label, $this);
	}



	public function setRowPrimaryKey($columnName)
	{
		$this->rowPrimaryKey = (string) $columnName;
	}



	public function getRowPrimaryKey()
	{
		return $this->rowPrimaryKey;
	}



	public function setColumnGetterCallback($getterCallback)
	{
		Callback::check($getterCallback);
		$this->columnGetterCallback = $getterCallback;
	}



	public function getColumnGetterCallback()
	{
		return $this->columnGetterCallback;
	}



	public function setDataSourceCallback($dataSourceCallback)
	{
		Callback::check($dataSourceCallback);
		$this->dataSourceCallback = $dataSourceCallback;
	}



	public function getDataSourceCallback()
	{
		return $this->dataSourceCallback;
	}



	public function setEditFormFactory($editFormFactory)
	{
		$this->editFormFactory = $editFormFactory;
	}



	public function getEditFormFactory()
	{
		return $this->editFormFactory;
	}



	public function setEditFormCallback($editFormCallback)
	{
		Callback::check($editFormCallback);
		$this->editFormCallback = $editFormCallback;
	}



	public function getEditFormCallback()
	{
		return $this->editFormCallback;
	}



	public function setFilterFormFactory($filterFormFactory)
	{
		Callback::check($filterFormFactory);
		$this->filterFormFactory = $filterFormFactory;
	}

	public function setFilterDefaults($defaults)
	{
		$this->filterDefaults = $defaults;
	}


	public function getFilterFormFactory()
	{
		return $this->filterFormFactory;
	}



	public function setPagination($itemsPerPage, $itemsCountCallback = NULL)
	{
		if ($itemsPerPage === FALSE) {
			$this->paginator = NULL;
			$this->paginatorItemsCountCallback = NULL;
		} else {
			if ($itemsCountCallback === NULL) {
				throw new \InvalidArgumentException('Items count callback must be set.');
			}

			Callback::check($itemsCountCallback);
			$this->paginator = new Paginator();
			$this->paginator->itemsPerPage = $itemsPerPage;
			$this->paginatorItemsCountCallback = $itemsCountCallback;
		}
	}



	public function addCellsTemplate($path)
	{
		$this->cellsTemplates[] = $path;
	}



	public function getCellsTemplate()
	{
		return $this->cellsTemplates;
	}



	public function setTranslator(ITranslator $translator)
	{
		$this->translator = $translator;
	}



	public function getTranslator()
	{
		return $this->translator;
	}



	public function translate($s, $count = NULL)
	{
		$translator = $this->getTranslator();
		return $translator === NULL || $s == NULL || $s instanceof Html // intentionally ==
			? $s
			: $translator->translate((string) $s, $count);
	}



	/*******************************************************************************/



	public function render()
	{
		$this->template->separateFilter = $this->separateFilter;
		$this->template->render = NULL;
		$this->renderDefault();
	}

	public function renderFilter() {
		$this->template->render = 'filter';
		$this->renderDefault();
	}

	protected function renderDefault() {
		if ($this->filterFormFactory) {
			$this['form']['filter']->setDefaults($this->filter);
		}

		$this->template->showPaginator = $this->showPaginator;
		$this->template->redrawOnlyRows = $this->redrawOnlyRows;
		$this->template->data = $this->getData();
		$this->template->columns = $this->columns;
		$this->template->editRowKey = $this->editRowKey;
		$this->template->rowPrimaryKey = $this->rowPrimaryKey;
		$this->template->paginator = $this->paginator;

		foreach ($this->cellsTemplates as &$cellsTemplate) {
			if ($cellsTemplate instanceof IFileTemplate) {
				$cellsTemplate = $cellsTemplate->getFile();
			}
			if (!file_exists($cellsTemplate)) {
				throw new \RuntimeException("Cells template '{$cellsTemplate}' does not exist.");
			}
		}

		$nextPageUrl = NULL;
		if ($this->paginator) {
			if (! $this->paginator->isLast()) {
				$nextPageUrl = $this->link('paginate!', ['page' => $this->paginator->page + 1]);
			}

			if ($this->presenter->isAjax()) {
				$this->presenter->payload->state[$this->getUniqueId() .'-nextPageUrl'] = $nextPageUrl;
			}
		}
		$this->template->nextPageUrl = $nextPageUrl;

		$this->template->cellsTemplates = $this->cellsTemplates;
		$this->template->showFilterCancel = $this->filterDataSource != $this->filterDefaults; // @ intentionaly
		$this->template->setFile($this->templateFile);
		
		$errorReporting = error_reporting();
		error_reporting(E_ALL & ~E_USER_DEPRECATED & ~E_USER_WARNING);
		$this->template->render();
		error_reporting($errorReporting);
	}

	function setTemplateFile($templateFile) {
		$this->templateFile = $templateFile;
	}

	/** @deprecated */
	function invalidateControl($snippet = NULL)
	{
		$this->redrawControl($snippet);
	}

	/** @deprecated */
	function validateControl($snippet = NULL)
	{
		$this->redrawControl($snippet, FALSE);
	}

	/** @deprecated */
	function invalidateRow($primaryValue)
	{
		$this->redrawRow($primaryValue);
	}


	public function redrawRow($primaryValue)
	{
		if ($this->presenter->isAjax()) {
			if (isset($this->filterDataSource[$this->rowPrimaryKey])) {
				$this->filterDataSource = array($this->rowPrimaryKey => $this->filterDataSource[$this->rowPrimaryKey]);
				if (is_string($this->filterDataSource[$this->rowPrimaryKey])) {
					$this->filterDataSource[$this->rowPrimaryKey] = array($this->filterDataSource[$this->rowPrimaryKey]);
				}
			} else {
				$this->filterDataSource = array();
			}

			$this->filterDataSource[$this->rowPrimaryKey][] = $primaryValue;
			parent::redrawControl('rows');
			$this->redrawControl('rows-' . $primaryValue);
		}
	}



	/*******************************************************************************/



	protected function attached($presenter): void
	{
		$this->filterDataSource = $this->filter;

		if ($this->isFormCreated) {
			if ($this->filterFormFactory) {
				if ($this->persistentFilter) {
					$this->loadPersistentFilterData($this['form']);
				}
			}
		}
	}

	/**
	 * Naplní filter form daty ze session. Předpokládá připojení komponenty
	 * k prezenteru.
	 */
	protected function loadPersistentFilterData($form) {
		if (! isset($form['filter'])) return;

		$session = $this->getSession();

		if (empty($session->filter['filter'])) return;

		$form['filter']->setDefaults($session->filter['filter']);
		$this->setFilterDataSourceFromFilterForm($form['filter']);
	}

	protected function savePersistentFilterData($form) {
		$session = $this->getSession();
		$session->filter = [
			'filter' => $form['filter']->getUnsafeValues(NULL),
		];
	}

	protected function setFilterDataSourceFromFilterForm($filterForm) {
		$this->filterDataSource = $this->filterFilterDataSourceFromFilterForm(
			$filterForm->getUnsafeValues(NULL)
		);
	}

	protected function filterFilterDataSourceFromFilterForm($values) {
		$result = [];

		foreach ($values as $k => $v) {
			if (in_array($v, ["", FALSE, NULL, []], TRUE)) {
				continue;
			}

			$result[$k] = is_array($v) ? $this->filterFilterDataSourceFromFilterForm($v) : $v;
		}

		return $result;
	}

	protected function getData($key = NULL)
	{
		if (!$this->data) {
			$onlyRow = $key !== NULL && $this->presenter->isAjax();
			if (!$onlyRow && $this->paginator) {
				if ($this->paginatorItemsCountCallback) {
					$itemsCount = call_user_func(
						$this->paginatorItemsCountCallback,
						$this->filterDataSource,
						$this->orderColumn ? array($this->orderColumn, strtoupper($this->orderType)) : NULL,
					);

					$this->paginator->setItemCount($itemsCount);
					if ($this->paginator->page !== $this->page) {
						$this->paginator->page = $this->page = 1;
					}
				}
			}

			if(!$this->data) {
				$this->data = call_user_func(
					$this->dataSourceCallback,
					$this->filterDataSource,
					$this->orderColumn ? array($this->orderColumn, strtoupper($this->orderType)) : NULL,
					$onlyRow ? NULL : $this->paginator
				);
			}
		}

		if ($key === NULL) {
			return $this->data;
		}

		foreach ($this->data as $row) {
			if ($this->getter($row, $this->rowPrimaryKey) == $key) {
				return $row;
			}
		}

		throw new \Exception('Row not found');
	}



	/**
	 * @internal
	 * @ignore
	 */
	public function getter($row, $column, $need = TRUE)
	{
		if ($this->columnGetterCallback) {
			return call_user_func($this->columnGetterCallback, $row, $column, $need);
		} else {
			if (
				(is_object($row) && !isset($row->$column))
				||
				(!is_object($row) && !isset($row[$column]))
			) {
				if ($need) {
					throw new \InvalidArgumentException("Result row does not have '{$column}' column.");
				} else {
					return NULL;
				}
			}

			return is_object($row) ? $row->$column : $row[$column];
		}
	}



	public function handleEdit($primaryValue, $cancelEditPrimaryValue = NULL)
	{
		$this->editRowKey = $primaryValue;
		$this->redrawRow($primaryValue);
		if ($cancelEditPrimaryValue) {
			foreach (explode(',', $cancelEditPrimaryValue) as $pv) {
				$this->redrawRow($pv);
			}
		}
	}



	public function handleSort()
	{
		if ($this->presenter->isAjax()) {
			$this->redrawControl('rows');
		} else {
			$this->redirect('this');
		}
	}



	public function createComponentForm()
	{
		$form = new UI\Form($this, 'form');

		$form->getElementPrototype()->class[] = 'ajax';

		if ($this->filterFormFactory) {
			$_filter = $this->getFilterFormFactory()($form);
			if(empty($form['filter'])) {
				$form['filter'] = $_filter;
			}

			if (!isset($form['filter']['filter'])) {
				$form['filter']->addSubmit('filter', $this->translate('Filter'));
			}
			if (!isset($form['filter']['cancel'])) {
				$form['filter']->addSubmit('cancel', $this->translate('Cancel'));
			}

			$form['filter']->setDefaults($this->filterDefaults);

			if (!$this->filterDataSource) {
				$this->setFilterDataSourceFromFilterForm($form['filter']);
			}
		}

		if ($this->editFormFactory && ($this->editRowKey !== NULL || !empty($_POST['edit']))) {
			$data = $this->editRowKey !== NULL && empty($_POST) ? $this->getData($this->editRowKey) : NULL;
			$form['edit'] = call_user_func($this->editFormFactory, $data);

			if (!isset($form['edit']['save']))
				$form['edit']->addSubmit('save', 'Save');
			if (!isset($form['edit']['cancel']))
				$form['edit']->addSubmit('cancel', 'Cancel');
			if (!isset($form['edit'][$this->rowPrimaryKey]))
				$form['edit']->addHidden($this->rowPrimaryKey);

			$form['edit'][$this->rowPrimaryKey]
				->setDefaultValue($this->editRowKey)
				->setOption('rendered', TRUE);
		}

		if ($this->filterFormFactory) {
			if ($this->persistentFilter) {
				if ($this->presenter) {
					$this->loadPersistentFilterData($form);
				}
			}
		}

		if ($this->translator) {
			$form->setTranslator($this->translator);
		}

		$form->onSuccess[] = function() {}; // fix for Nette Framework 2.0.x
		$form->onSubmit[] = [$this, 'processForm'];
		return $form;
	}



	public function processForm(UI\Form $form)
	{
		$allowRedirect = TRUE;
		if (isset($form['edit'])) {
			if ($form['edit']['save']->isSubmittedBy()) {
				if ($form['edit']->isValid()) {
					call_user_func($this->editFormCallback, $form['edit']);
				} else {
					$this->editRowKey = $form['edit'][$this->rowPrimaryKey]->getValue();
					$allowRedirect = FALSE;
				}
			}
			if ($form['edit']['cancel']->isSubmittedBy() || ($form['edit']['save']->isSubmittedBy() && $form['edit']->isValid())) {
				$editRowKey = $form['edit'][$this->rowPrimaryKey]->getValue();
				$this->redrawRow($editRowKey);
				$this->getData($editRowKey);
			}
			if ($this->editRowKey !== NULL) {
				$this->redrawRow($this->editRowKey);
			}
		}

		if (isset($form['filter'])) {
			if ($form['filter']['filter']->isSubmittedBy()) {
				$values = $form['filter']->getUnsafeValues('array');
				unset($values['filter']);
				$values = $this->filterFormFilter($values);
				if ($this->paginator) {
					$this->page = $this->paginator->page = 1;
				}
				$this->filter = $this->filterDataSource = $values;
				$this->redrawControl('rows');
				if ($this->separateFilter) {
					$this->redrawControl('filter');
				}
			} elseif ($form['filter']['cancel']->isSubmittedBy()) {
				if ($this->paginator) {
					$this->page = $this->paginator->page = 1;
				}
				$this->filter = $this->filterDataSource = $this->filterDefaults;
				$form['filter']->setValues($this->filter, TRUE);
				$this->redrawControl('rows');
				if ($this->separateFilter) {
					$this->redrawControl('filter');
				}
			}

			if ($this->persistentFilter) {
				$this->savePersistentFilterData($form);
			}
		}

		if (!$this->presenter->isAjax() && $allowRedirect) {
			$this->redirect('this');
		}
	}



	public function loadState(array $params): void
	{
		parent::loadState($params);

		if ($this->paginator) {
			$this->paginator->page = $this->page;
		}
	}



	protected function createTemplate($class = NULL): UI\ITemplate
	{
		$template = parent::createTemplate($class);
		if ($translator = $this->getTranslator()) {
			$template->setTranslator($translator);
		}
		return $template;
	}



	public function handlePaginate()
	{
		if ($this->presenter->isAjax()) {
			$this->redrawControl('rows');
		} else {
			$this->redirect('this');
		}
	}


	private function filterFormFilter($values)
	{
		return array_filter($values, function($val) {
			if (is_array($val)) {
				return !empty($val);
			}
			if (is_string($val)) {
				return strlen($val) > 0;
			}
			return $val !== null;
		});
	}

	public function saveState(array &$params, $reflection = NULL): void {
		parent::saveState($params, $reflection);

		if (isset($params['filter'])) {
			foreach ($params['filter'] as $k => $v) {
				if ($v instanceof \DateTime) {
					$params['filter'][$k] = $v->format('Y-m-d\TH:i:se');
				}
			}
		}

	}

	public function setShowActionsColumn($show = TRUE) {
		$this->showActionsColumn = $show;
	}

	public function getShowActionsColumn() {
		return $this->showActionsColumn;
	}

	public function redrawControl($snippet = null, $redraw = true): void
	{
		if ($snippet === null && $this->separateFilter === false) {
			parent::redrawControl('rows', $redraw);
		} else {
			parent::redrawControl($snippet, $redraw);
		}
	}

}
