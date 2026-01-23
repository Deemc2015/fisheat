<?if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED!==true) die();

if(!CModule::IncludeModule("iblock")){
    return;
}


$arIBlockType = CIBlockParameters::GetIBlockTypes();
$arIBlock=array(
    "-" => GetMessage("IBLOCK_ANY"),
);

$rsIBlock = CIBlock::GetList(Array("sort" => "asc"), Array("TYPE" => $arCurrentValues["IBLOCK_TYPE"], "ACTIVE"=>"Y"));
while($arr=$rsIBlock->Fetch())
{
    $arIBlock[$arr["ID"]] = "[".$arr["ID"]."] ".$arr["NAME"];
}

$property_enums = CIBlockPropertyEnum::GetList(["SORT"=>"ASC"], ["IBLOCK_ID"=>$arCurrentValues["IBLOCK_ID"], "CODE"=>"TYPE"]);
$officeType = [];
while($enum_fields = $property_enums->GetNext())
{
    $officeType[$enum_fields["ID"]] = $enum_fields["VALUE"];
}


$arComponentParameters = array(
    "GROUPS" => array(
    ),
    "PARAMETERS" => array(
        "CACHE_TIME"  =>  Array("DEFAULT"=>3600000),
        "IBLOCK_TYPE" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("IBLOCK_TYPE"),
            "TYPE" => "LIST",
            "VALUES" => $arIBlockType,
            "REFRESH" => "Y",
        ),
        "IBLOCK_ID" => array(
            "PARENT" => "BASE",
            "NAME" => GetMessage("IBLOCK_IBLOCK"),
            "TYPE" => "LIST",
            "VALUES" => $arIBlock,
            "REFRESH" => "Y",
        )
    ),
);
?>
