<?php
$arUrlRewrite=array (
  9 => 
  array (
    'CONDITION' => '#^/personal/moi-zakazy/#',
    'RULE' => '',
    'ID' => 'bitrix:sale.personal.order',
    'PATH' => '/personal/moi-zakazy/index.php',
    'SORT' => 100,
  ),
  13 => 
  array (
    'CONDITION' => '#^([^/]+?)\\??(.*)#',
    'RULE' => 'SECTION_CODE=$1&$2',
    'ID' => 'bitrix:catalog.section',
    'PATH' => '/index.php',
    'SORT' => 100,
  ),
  10 => 
  array (
    'CONDITION' => '#^/loyalty/#',
    'RULE' => NULL,
    'ID' => 'skyweb24:loyaltyprogram',
    'PATH' => '/loyalty/index.php',
    'SORT' => 100,
  ),
  11 => 
  array (
    'CONDITION' => '#^/catalog/#',
    'RULE' => '',
    'ID' => 'bitrix:catalog',
    'PATH' => '/catalog/index.php',
    'SORT' => 100,
  ),
  12 => 
  array (
    'CONDITION' => '#^/actions/#',
    'RULE' => '',
    'ID' => 'bitrix:news',
    'PATH' => '/actions/index.php',
    'SORT' => 100,
  ),
  7 => 
  array (
    'CONDITION' => '#^\\??(.*)#',
    'RULE' => '&$1',
    'ID' => 'bitrix:catalog.top',
    'PATH' => '/izbrannye-tovary/index.php',
    'SORT' => 100,
  ),
);
