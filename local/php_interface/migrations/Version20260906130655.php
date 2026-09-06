<?php

namespace Sprint\Migration;


class Version20260906130655 extends Version{
    protected $author = "79177695923";

    protected $description = "";

    protected $moduleVersion = "5.6.1";

    /**
    * @throws Exceptions\HelperException
    * @return bool|void
    */
    public function up()
    {
        $helper = $this->getHelperManager();
                $helper->OrderProperties()->saveOrderProperty(array (
  'ID' => '7',
  'PERSON_TYPE_ID' => '1',
  'NAME' => 'Адрес доставки',
  'TYPE' => 'STRING',
  'REQUIRED' => 'N',
  'DEFAULT_VALUE' => '',
  'SORT' => '150',
  'USER_PROPS' => 'Y',
  'IS_LOCATION' => 'N',
  'PROPS_GROUP_ID' => '2',
  'DESCRIPTION' => '',
  'IS_EMAIL' => 'N',
  'IS_PROFILE_NAME' => 'N',
  'IS_PAYER' => 'N',
  'IS_LOCATION4TAX' => 'N',
  'IS_FILTERED' => 'N',
  'CODE' => 'ADDRESS',
  'IS_ZIP' => 'N',
  'IS_PHONE' => 'N',
  'IS_ADDRESS' => 'Y',
  'IS_ADDRESS_FROM' => 'N',
  'IS_ADDRESS_TO' => 'N',
  'ACTIVE' => 'Y',
  'UTIL' => 'N',
  'INPUT_FIELD_LOCATION' => '0',
  'MULTIPLE' => 'N',
  'SETTINGS' => 
  array (
    'MINLENGTH' => '',
    'MAXLENGTH' => '',
    'PATTERN' => '',
    'MULTILINE' => 'Y',
    'COLS' => '30',
    'ROWS' => '3',
  ),
  'ENTITY_REGISTRY_TYPE' => 'ORDER',
  'XML_ID' => '',
  'ENTITY_TYPE' => 'ORDER',
), 'code');
        }
}
