<?php

/**
 * Creates a field that allows multiple selection, like a CheckboxSetField
 * to store in a many_many, has_many, or native field (comma separated values)
 * and provides typeahead searching on a given result set. Useful for multiple
 * selection of a densely populated data set, like tags.
 *
 * @author  Uncle Cheese <unclecheese@leftandmain.com>
 * @package  silverstripe-bootstrap-tagfield
 */
class BootstrapTagField extends CheckboxSetField {

	/**
	 * A list of allowed action
	 * @var array
	 */
	private static $allowed_actions = array (
		'query',
		'prefetch'
	);
	
	/**
	 * A list of records to prefetch, for instant response on typeahead.
	 * 	
	 * @var SS_List
	 */
	protected $prefetch;

	/**
	 * The field that will serve as the visible representation of the record, e.g. "Title"
	 * @var string
	 */
	protected $labelField;

	/**
	 * The field that will be stored in the database, e.g. "ID"
	 * @var string
	 */
	protected $idField;

	/**
	 * Constructor
	 * 	
	 * @param string $name
	 * @param string $title
	 * @param SS_List $source
	 * @param string $labelField
	 * @param string $idField
	 * @param string $value
	 * @param Form $form
	 */
	public function __construct($name, $title=null, $source = null, $labelField = 'Title', $idField = 'ID', $value='', $form=null) {
		if($source && !$source instanceof SS_List) {
			throw new Exception("BootstrapTagField::__construct() -- \$source must be an SS_List");
		}
		else if(!$source) {
			$source = ArrayList::create();
		}

		$this->labelField = $labelField;
		$this->idField = $idField;

		parent::__construct($name, $title, $source, $value, $form);
	}

	/**
	 * Formats JSON so that it is usable by the JS component
	 * 
	 * @param  SS_List $list The list to format
	 * @return string        JSON
	 */
	protected function formatJSON(SS_List $list) {
		$ret = array ();
		foreach($list as $item) {
			$ret[] = array(
				'id' => $item->{$this->idField},
				'label' => $item->{$this->labelField}
			);
		}

		return Convert::array2json($ret);
	}

	/**
	 * An AJAX endpoint for querying the typeahead
	 * 
	 * @param  SS_HTTPRequest $r The request
	 * @return string            JSON
	 */
	public function query(SS_HTTPRequest $r) {
		return $this->formatJSON($this->source->filter(array(
			$this->labelField.':PartialMatch' => $r->getVar('q')
		))
		->limit(10));
	}

	/**
	 * An AJAX endpoint for getting the prefetch JSON
	 * 	
	 * @param  SS_HTTPRequest $r The request
	 * @return string 			JSON
	 */
	public function prefetch(SS_HTTPRequest $r) {
		if($this->prefetch) {
			return $this->formatJSON($this->prefetch);
		}
	}

	/**
	 * Gets the current values assigned to the field, formatted as a JSON array
	 * 	
	 * @return string 			JSON
	 */
	protected function getValuesJSON() {
		$value = $this->value;
		if($value instanceof SS_List) {
			$values = $value->column($this->idField);
		}
		else if(is_array($value)) {
			$values = array_keys($value);
		}
		else if(is_string($value)) {
			$values = explode(',', $values);
			$values = str_replace('{comma}', ',', $values);
		}

		return $this->formatJSON($this->source->filter(array(
			$this->idField => $values
		)));
	}

	/**
	 * Sets the prefetch records list
	 * 	
	 * @param SS_List $list
	 * @return  BootstrapTagField
	 */
	public function setPrefetch(SS_List $list) {
		if(!$list instanceof SS_List) {
			throw new Exception('Prefetch list must be an instance of SS_List');
		}

		$this->prefetch = $list;

		return $this;
	}

	/**
	 * Renders the field
	 *
	 * @param  array $properties
	 * @return  SSViewer
	 */
	public function Field($properties = array()) {
		Requirements::javascript(BOOTSTRAP_TAGFIELD_DIR.'/javascript/typeahead.js');
		Requirements::javascript(BOOTSTRAP_TAGFIELD_DIR.'/javascript/bootstrap-tagfield.js');
		Requirements::javascript(BOOTSTRAP_TAGFIELD_DIR.'/javascript/bootstrap-tagfield-init.js');
		Requirements::css(BOOTSTRAP_TAGFIELD_DIR.'/css/bootstrap-tagfield.css');

		$this->setAttribute('data-value', $this->getValuesJSON())
			 ->setAttribute('data-bootstrap-tags', true)
			 ->setAttribute('data-query-url', $this->Link('query'))
			 ->setAttribute('data-prefetch-url', $this->Link('prefetch'))
			 ->setAttribute('class', 'text');
		
		return $this->renderWith(
			$this->getTemplates()
		);
	}
}