<?php

namespace UncleCheese\BootstrapTagField;

use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\ORM\SS_List;
// use Exception;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Core\Convert;
use SilverStripe\Control\Controller;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\DataObjectInterface;
use SilverStripe\ORM\RelationList;
use SilverStripe\ORM\UnsavedRelationList;
use SilverStripe\View\Requirements;

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
	 * Determines whether free text is allowed
	 * 
	 * @var boolean
	 */
	protected $freeInput = false;

  /**
   * @var DataList
   */
  protected $sourceList;

  /**
   * @var string
   */
  protected $prefetchUrl;

  /**
   * @var string
   */
  protected $queryUrl;

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
		// if($source && !$source instanceof SS_List) {
		// 	throw new Exception("BootstrapTagField::__construct() -- \$source must be an SS_List");
		// }
		// else if(!$source) {
		// 	$source = ArrayList::create();
		// }

		$this->labelField = $labelField;
		$this->idField = $idField;
    $this->setSourceList($source);

		parent::__construct($name, $title, $source, $value, $form);
	}

	/**
	 * Formats JSON so that it is usable by the JS component
	 * 
	 * @param  SS_List $list The list to format
	 * @return string        JSON
	 */
	public static function formatJSON(SS_List $list, $idField = 'ID', $labelField = 'Title') {
		$ret = array ();
		foreach($list as $item) {
			$ret[] = array(
				'id' => $item->{$idField},
				'label' => $item->{$labelField}
			);
		}

		return Convert::array2json($ret);
	}

	/**
	 * An AJAX endpoint for querying the typeahead
	 * 
	 * @param  HTTPRequest $r The request
	 * @return string            JSON
	 */
	public function query(HTTPRequest $r) {
    $source = $this->getSourceList();
		return self::formatJSON($source->filter(array(
			$this->labelField.':PartialMatch' => $r->getVar('q')
		))
		->limit(10), $this->idField, $this->labelField);
	}

	/**
	 * An AJAX endpoint for getting the prefetch JSON
	 * 	
	 * @param  HTTPRequest $r The request
	 * @return string 			JSON
	 */
	public function prefetch(HTTPRequest $r) {
		if($this->prefetch) {
			return self::formatJSON($this->prefetch, $this->idField, $this->labelField);
		}
	}

	/**
	 * Gets the current values assigned to the field, formatted as a JSON array
	 * 	
	 * @return string 			JSON
	 */
	protected function getValuesJSON() {
    $source = $this->getSourceList();

		$value = $this->value;
		if($value instanceof SS_List) {
			$values = $value->column($this->idField);
		}
		else if(is_array($value)) {
			$values = array_keys($value);
		}
		else if(is_string($value)) {
			$values = explode(',', $value);
			$values = str_replace('{comma}', ',', $values);
		}
		return self::formatJSON($source->filter(array(
			$this->idField => $values
		)), $this->idField, $this->labelField);
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
	 * Enables input of free text, rather than binding to a set list of options
	 * 
	 * @param boolean $bool
	 * @return  BootstrapTagField
	 */
	public function setFreeInput($bool = true) {
		$this->freeInput = $bool;

		return $this;
	}


	public function setValue($val, $obj = null) {
    $source = $this->getSourceList();

		$values = array ();
		if(is_array($val)) {
			foreach($val as $id => $text) {
				if(preg_match('/^__new__/', $id)) {
					$id = $source->newObject(array(
						$this->labelField => $text
					))->write();
				}
				$values[$id] = $text;
			}
			parent::setValue($values, $obj);			
		}
		else {
			parent::setValue($val, $obj);
		}
		
	}

	/**
	 * Sets the label field
	 * 
	 * @param string $field
	 * @return  BootstrapTagField
	 */
	public function setLabelField($field) {
		$this->labelField = $field;

		return $this;
	}

	/**
	 * Sets the ID field
	 * 
	 * @param string $field
	 * @return  BootstrapTagField
	 */
	public function setIDField($field) {
		$this->idField = $field;

		return $this;
	}

	/**
	 * Save the current value into a DataObject.
	 * If the field it is saving to is a has_many or many_many relationship,
	 * it is saved by setByIDList(), otherwise it creates a comma separated
	 * list for a standard DB text/varchar field.
	 *
	 * @param DataObject $record The record to save into
	 */
	public function saveInto(DataObjectInterface $record) {
    $source = $this->getSourceList();

		$fieldname = $this->name;
		$relation = ($fieldname && $record && $record->hasMethod($fieldname)) ? $record->$fieldname() : null;
		if($fieldname && $record && $relation &&
			($relation instanceof RelationList || $relation instanceof UnsavedRelationList)) {
			$idList = array();
			if($this->value) foreach($this->value as $id => $text) {				
				if(preg_match('/^__new__/', $id)) {
					$id = $source->newObject(array(
						$this->labelField => $text
					))->write();
				}

				$idList[] = $id;
			}
			$relation->setByIDList($idList);
		} elseif($fieldname && $record) {
			if($this->value) {
				$this->value = str_replace(',', '{comma}', $this->value);
				$record->$fieldname = implode(',', (array) $this->value);
			} else {
				$record->$fieldname = '';
			}
		}
	}	

	/**
	 * Renders the field
	 *
	 * @param  array $properties
	 * @return  SSViewer
	 */
	public function Field($properties = array()) {
		// Requirements::javascript('_resources/strap-tagfield/javascript/typeahead.js');
		// Requirements::javascript('_resources/strap-tagfield/javascript/bootstrap-tagfield.js');
		// Requirements::javascript('_resources/strap-tagfield/javascript/bootstrap-tagfield-init.js');
    Requirements::javascript('_resources/strap-tagfield/javascript/bs-tagfield.js');
		Requirements::css('_resources/strap-tagfield/css/bootstrap-tagfield.css');

		$this->setAttribute('data-value', $this->getValuesJSON())
			 ->setAttribute('data-bootstrap-tags', true)
			 ->setAttribute('data-query-url', $this->getQueryUrl())
			 ->setAttribute('data-prefetch-url', $this->getPrefetchUrl())
			 ->setAttribute('data-freeinput', $this->freeInput)
			 ->setAttribute('class', 'text');
		
		return $this->renderWith(self::class);
	}

  /**
   * Gets the source array if required
   *
   * Note: this is expensive for a SS_List
   *
   * @return array
   */
  public function getSource()
  {
      if (is_null($this->source)) {
          $this->source = $this->getListMap($this->getSourceList());
      }
      return $this->source;
  }


  /**
   * Intercept DataList source
   *
   * @param mixed $source
   * @return $this
   */
  public function setSource($source)
  {
      // When setting a datalist force internal list to null
      if ($source instanceof DataList) {
          $this->source = null;
          $this->setSourceList($source);
      } else {
          parent::setSource($source);
      }
      return $this;
  }

  /**
   * Get the DataList source. The 4.x upgrade for SelectField::setSource starts to convert this to an array.
   * If empty use getSource() for array version
   *
   * @return DataList
   */
  public function getSourceList()
  {
      return $this->sourceList;
  }

  /**
   * Set the model class name for tags
   *
   * @param DataList $sourceList
   * @return self
   */
  public function setSourceList($sourceList)
  {
      $this->sourceList = $sourceList;
      return $this;
  }

  public function setPrefetchUrl($url){
    $this->prefetchUrl = $url;
    return $this;
  }

  public function getPrefetchUrl(){
    if($this->prefetchUrl){
      return $this->prefetchUrl;
    }
    return $this->Link('prefetch');
  }

  public function setQueryUrl($url){
    $this->queryUrl = $url;
    return $this;
  }

  public function getQueryUrl(){
    if($this->queryUrl){
      return $this->queryUrl;
    }
    return $this->Link('query');
  }
}