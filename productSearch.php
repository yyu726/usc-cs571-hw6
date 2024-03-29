<?php
// yyu726 Version 3.0
function searchRequest()
{
    global $form;
    if ($form == null) {
        return;
    }
    $OPERATION_NAME = 'findItemsAdvanced';
    $SERVICE_VERSION = '1.0.0';
    $SECURITY_APPNAME = 'YangYu-CSCI571-PRD-7a6d8bb94-950c1bc5';
    $RESPONSE_DATA_FORMAT = 'JSON';
    $paginationInput_entriesPerPage = '20';
    $keywords = $form["keywords"];
    $categoryArray = array('All' => NULL, 'Art' => '550', 'Baby' => '2984', 'Books' => '267', 'CSA' => '11450',
        'CTN' => '58058', 'HB' => '26395', 'Music' => '11233', 'VGC' => '1249');
    $categoryId = $categoryArray[$form["category"]];
    $buyerPostalCode = null;
    $MaxDistance = '10';
    if (isset($form["nearbySearch"])) {
        if (isset($form["hereRadio"])) {
            $buyerPostalCode = $form["hereRadio"];
        }
        if (isset($form["zipInput"])) {
            $buyerPostalCode = $form["zipInput"];
        }
        if ($form["distance"] != '') {
            $MaxDistance = $form["distance"];
        }
    }
    $LocalPickupOnly = isset($form["shipping1"]) ? "true" : NULL;
    $FreeShippingOnly = isset($form["shipping2"]) ? "true" : NULL;
    $Condition = null;
    if (isset($form["condition1"])) {
        $Condition[] = $form["condition1"];
    }
    if (isset($form["condition2"])) {
        $Condition[] = $form["condition2"];
    }
    if (isset($form["condition3"])) {
        $Condition[] = $form["condition3"];
    }
    $searchURL = "http://svcs.ebay.com/services/search/FindingService/v1?";
    $searchURL = $searchURL . "OPERATION-NAME=" . $OPERATION_NAME;
    $searchURL = $searchURL . "&SERVICE-VERSION=" . $SERVICE_VERSION;
    $searchURL = $searchURL . "&SECURITY-APPNAME=" . $SECURITY_APPNAME;
    $searchURL = $searchURL . "&RESPONSE-DATA-FORMAT=" . $RESPONSE_DATA_FORMAT;
    $searchURL = $searchURL . "&REST-PAYLOAD";
    $searchURL = $searchURL . "&paginationInput.entriesPerPage=" . $paginationInput_entriesPerPage;
    $searchURL = $searchURL . "&keywords=" . rawurlencode($keywords);
    if (isset($categoryId)) {
        $searchURL = $searchURL . "&categoryId=" . $categoryId;
    }

    $filterCount = 0;
    if (isset($buyerPostalCode)) {
        $searchURL = $searchURL . "&buyerPostalCode=" . $buyerPostalCode;
        if (isset($MaxDistance)) {
            $searchURL = $searchURL . "&itemFilter($filterCount).name=MaxDistance";
            $searchURL = $searchURL . "&itemFilter($filterCount).value=" . $MaxDistance;
            $filterCount++;
        }
    }

    if (isset($FreeShippingOnly)) {
        $searchURL = $searchURL . "&itemFilter($filterCount).name=FreeShippingOnly";
        $searchURL = $searchURL . "&itemFilter($filterCount).value=" . $FreeShippingOnly;
        $filterCount++;
    }
    if (isset($LocalPickupOnly)) {
        $searchURL = $searchURL . "&itemFilter($filterCount).name=LocalPickupOnly";
        $searchURL = $searchURL . "&itemFilter($filterCount).value=" . $LocalPickupOnly;
        $filterCount++;
    }
    $searchURL = $searchURL . "&itemFilter($filterCount).name=HideDuplicateItems";
    $searchURL = $searchURL . "&itemFilter($filterCount).value=true";
    $filterCount++;
    if (!is_null($Condition)) {
        $searchURL = $searchURL . "&itemFilter($filterCount).name=Condition";
        for ($i = 0; $i < count($Condition); $i++) {
            $searchURL = $searchURL . "&itemFilter($filterCount).value($i)=" . $Condition[$i];
        }
        $filterCount++;
    }
    $searchText = file_get_contents($searchURL);

    $searchObj = json_decode($searchText);

    searchProcess($searchObj);

}

function searchProcess($searchObj)
{
    global $itemJSON;
    $itemJSON = null;

    if ($searchObj->{'findItemsAdvancedResponse'}[0]->{'ack'}[0] == 'Failure') {
        $itemJSON['Ack'] = 'Failure';
        if (isset($searchObj->{'findItemsAdvancedResponse'}[0]->{'errorMessage'}[0]->{'error'}[0]->{'message'}[0])) {
            $itemJSON['ErrorMessage'] = $searchObj->{'findItemsAdvancedResponse'}[0]->{'errorMessage'}[0]->{'error'}[0]->{'message'}[0];
            if ($itemJSON['ErrorMessage'] == 'Invalid numeric value.') {
                $itemJSON['ErrorMessage'] = 'Distance is invalid';
            }
            if ($itemJSON['ErrorMessage'] == 'Invalid postal code for specified country.') {
                $itemJSON['ErrorMessage'] = 'Zipcode is invalid';
            }
        } else {
            $itemJSON['ErrorMessage'] = 'Invalid Input. Search Failed.';
        }
        $itemJSON = json_encode($itemJSON);

        return;
    } else {
        $itemJSON['Ack'] = 'Success';
    }

    $searchResult = $searchObj->{'findItemsAdvancedResponse'}[0]->{'searchResult'}[0];
    $itemCount = $searchResult->{'@count'};
    $item = array();
    for ($i = 0; $i < $itemCount; $i++) {
        $itemTemp = $searchResult->{'item'}[$i];
        $item[$i]["Index"] = $i + 1;
        $item[$i]["Photo"] = isset($itemTemp->{'galleryURL'}[0]) ? $itemTemp->{'galleryURL'}[0] : 'N/A';
        $item[$i]["Name"] = isset($itemTemp->{'title'}[0]) ? $itemTemp->{'title'}[0] : 'N/A';
        $item[$i]["Price"]["Currency"] = isset($itemTemp->{'sellingStatus'}[0]->{'currentPrice'}[0]->{'@currencyId'}) ? $itemTemp->{'sellingStatus'}[0]->{'currentPrice'}[0]->{'@currencyId'} : 'N/A';
        $item[$i]["Price"]["Value"] = isset($itemTemp->{'sellingStatus'}[0]->{'currentPrice'}[0]->{'__value__'}) ? $itemTemp->{'sellingStatus'}[0]->{'currentPrice'}[0]->{'__value__'} : 'N/A';
        //$item[$i]["Price"] = $itemTemp->{'sellingStatus'}[0]->{'currentPrice'}[0]->{'@currencyId'} . $itemTemp->{'sellingStatus'}[0]->{'currentPrice'}[0]->{'__value__'};
        $item[$i]["Zip"] = isset($itemTemp->{'postalCode'}[0]) ? $itemTemp->{'postalCode'}[0] : 'N/A';
        $item[$i]["Condition"] = isset($itemTemp->{'condition'}[0]) ? $itemTemp->{'condition'}[0]->{'conditionDisplayName'}[0] : 'N/A';
        if (isset($itemTemp->{'shippingInfo'}[0]->{'shippingServiceCost'}[0]->{'__value__'})) {
            $item[$i]["Shipping"] = $itemTemp->{'shippingInfo'}[0]->{'shippingServiceCost'}[0]->{'__value__'};
            if ($item[$i]["Shipping"] == '0.0') {
                $item[$i]["Shipping"] = 'FreeShipping';
            } else {
                $item[$i]["Shipping"] = '$' . $item[$i]["Shipping"];
            }
        } else {
            $item[$i]["Shipping"] = 'N/A';
        }
        $item[$i]["ItemId"] = $itemTemp->{'itemId'}[0];
    }

    $itemJSON['Header'] = array('Index', 'Photo', 'Name', 'Price', 'Zip code', 'Condition', 'Shipping Option');
    $itemJSON['Item'] = $item;
    $itemJSON = json_encode($itemJSON);

}

function detailRequest($itemId)
{
    if ($itemId == null) {
        return;
    }
    $callName = 'GetSingleItem';
    $responseencoding = 'JSON';
    $appid = 'YangYu-CSCI571-PRD-7a6d8bb94-950c1bc5';
    $siteid = '0';
    $version = '967';
    $IncludeSelector = 'Description,Details,ItemSpecifics';

    $detailURL = 'http://open.api.ebay.com/shopping?';
    $detailURL = $detailURL . 'callname=' . $callName;
    $detailURL = $detailURL . '&responseencoding=' . $responseencoding;
    $detailURL = $detailURL . '&appid=' . $appid;
    $detailURL = $detailURL . '&siteid=' . $siteid;
    $detailURL = $detailURL . '&version=' . $version;
    $detailURL = $detailURL . '&ItemID=' . $itemId;
    $detailURL = $detailURL . '&IncludeSelector=' . $IncludeSelector;

    $detailText = file_get_contents($detailURL);

    $detailObj = json_decode($detailText);

    detailProcess($detailObj);
}

function detailProcess($detailObj)
{
    $detail = null;
    if ($detailObj->{'Ack'} == 'Failure') {
        $detail['Ack'] = 'Failure';
        $seller = '';
    }
    if ($detailObj->{'Ack'} == 'Success') {
        $seller['Description'] = $detailObj->{'Item'}->{'Description'};
        $detail[0]['Photo'] = isset($detailObj->{'Item'}->{'PictureURL'}[0]) ? $detailObj->{'Item'}->{'PictureURL'}[0] : 'N/A';
        $detail[0]['Title'] = isset($detailObj->{'Item'}->{'Title'}) ? $detailObj->{'Item'}->{'Title'} : 'N/A';
        $detail[0]['Subtitle'] = isset($detailObj->{'Item'}->{'Subtitle'}) ? $detailObj->{'Item'}->{'Subtitle'} : 'N/A';
        $detail_currency = isset($detailObj->{'Item'}->{'CurrentPrice'}->{'CurrencyID'}) ? $detailObj->{'Item'}->{'CurrentPrice'}->{'CurrencyID'} : 'N/A';
        $detail_value = isset($detailObj->{'Item'}->{'CurrentPrice'}->{'Value'}) ? $detailObj->{'Item'}->{'CurrentPrice'}->{'Value'} : 'N/A';
        if ($detail_currency == 'N/A' && $detail_value == 'N/A') {
            $detail[0]['Price'] = 'N/A';
        } else {
            $detail[0]['Price'] =  $detail_value . " " . $detail_currency ;
        }
        $detail[0]['Location'] = isset($detailObj->{'Item'}->{'Location'}) ? $detailObj->{'Item'}->{'Location'} : 'N/A';
        $detail[0]['PostalCode'] = isset($detailObj->{'Item'}->{'PostalCode'}) ? $detailObj->{'Item'}->{'PostalCode'} : 'N/A';
        $detail[0]['Seller'] = isset($detailObj->{'Item'}->{'Seller'}->{'UserID'}) ? $detailObj->{'Item'}->{'Seller'}->{'UserID'} : 'N/A';
        $detail_return_accepted = isset($detailObj->{'Item'}->{'ReturnPolicy'}->{'ReturnsAccepted'}) ? $detailObj->{'Item'}->{'ReturnPolicy'}->{'ReturnsAccepted'} : 'N/A';
        $detail_return_within = isset($detailObj->{'Item'}->{'ReturnPolicy'}->{'ReturnsWithin'}) ? ' Within ' . $detailObj->{'Item'}->{'ReturnPolicy'}->{'ReturnsWithin'} : 'N/A';
        if ($detail_return_accepted == 'N/A' && $detail_return_within == 'N/A') {
            $detail[0]['ReturnPolicy'] = 'N/A';
        } else if ($detail_return_accepted != 'N/A' && $detail_return_within == 'N/A') {
            $detail[0]['ReturnPolicy'] = $detail_return_accepted;
        } else {
            $detail[0]['ReturnPolicy'] = $detail_return_accepted . $detail_return_within;
        }
        $detail[0]['ItemSpecifics'] = isset($detailObj->{'Item'}->{'ItemSpecifics'}->{'NameValueList'}) ? $detailObj->{'Item'}->{'ItemSpecifics'}->{'NameValueList'} : array();
    }
    global $sellerJSON;
    global $detailJSON;
    $sellerJSON = json_encode($seller);
    $detailJSON = json_encode($detail);

}

function similarRequest($itemId)
{
    if ($itemId == null) {
        return;
    }
    $OPERATION_NAME = 'getSimilarItems';
    $SERVICE_NAME = 'MerchandisingService';
    $SERVICE_VERSION = '1.1.0';
    $CONSUMER_ID = 'YangYu-CSCI571-PRD-7a6d8bb94-950c1bc5';
    $RESPONSE_DATA_FORMAT = 'JSON';
    $maxResults = 8;
    $similarURL = 'http://svcs.ebay.com/MerchandisingService?';
    $similarURL = $similarURL . 'OPERATION-NAME=' . $OPERATION_NAME;
    $similarURL = $similarURL . '&SERVICE-NAME=' . $SERVICE_NAME;
    $similarURL = $similarURL . '&SERVICE-VERSION=' . $SERVICE_VERSION;
    $similarURL = $similarURL . '&CONSUMER-ID=' . $CONSUMER_ID;
    $similarURL = $similarURL . '&RESPONSE-DATA-FORMAT=' . $RESPONSE_DATA_FORMAT;
    $similarURL = $similarURL . '&REST-PAYLOAD';
    $similarURL = $similarURL . '&itemId=' . $itemId;
    $similarURL = $similarURL . '&maxResults=' . $maxResults;

    $similarText = file_get_contents($similarURL);

    $similarObj = json_decode($similarText);

    similarProcess($similarObj);
}

function similarProcess($similarObj)
{

    $similar = array();
    $similarItem = $similarObj->{'getSimilarItemsResponse'}->{'itemRecommendations'}->{'item'};
    for ($i = 0; $i < count($similarItem); $i++) {
        $similar[$i]['ItemID'] = isset($similarItem[$i]->{'itemId'}) ? $similarItem[$i]->{'itemId'} : 'N/A';
        $similar[$i]['Title'] = isset($similarItem[$i]->{'title'}) ? $similarItem[$i]->{'title'} : 'N/A';
        $similar[$i]['Photo'] = isset($similarItem[$i]->{'imageURL'}) ? $similarItem[$i]->{'imageURL'} : 'N/A';
        $similar[$i]['Price'] = isset($similarItem[$i]->{'buyItNowPrice'}->{'__value__'}) ? '$' . $similarItem[$i]->{'buyItNowPrice'}->{'__value__'} : 'N/A';
        if ($similar[$i]['Price'] == '$0.00' && isset($similarItem[$i]->{'currentPrice'}->{'__value__'})) {
            $similar[$i]['Price'] = '$' . $similarItem[$i]->{'currentPrice'}->{'__value__'};
        }
    }

    global $similarJSON;
    $similarJSON = json_encode($similar);

}

$form = isset($_POST["keywords"]) ? $_POST : null;
$itemJSON = null;
searchRequest();
$itemId = isset($_POST["itemIdInput"]) ? $itemId = $_POST["itemIdInput"] : null;
$detailJSON = null;
$sellerJSON = null;
$similarJSON = null;
detailRequest($itemId);
similarRequest($itemId);

?>

<html>

<head>
    <title>Pruduct Search</title>
    <style>
        :root {
            --bodyWidth: 700px;
        }

        body {
            margin: 0px;
            padding: 0px;
            border: 0px;
            font-family: Times New Roman;
        }

        body {
            width: var(--siteWidth);
        }

        div.divForm {
            width: var(--bodyWidth);
            margin: auto;
            text-align: center;
            border-style: solid;
            border-color: rgb(175, 175, 175);
            background-color: rgb(250,250,250);
        }

        div.formTitle {
            width: var(--bodyWidth);
            height: 55px;
        }

        p.formTitle {
            margin: 0px;
            height: 50px;
            font-style: italic;
            font-size: 40px;
            color: black;
        }

        .divideLine {
            margin: auto;
            width: calc(var(--bodyWidth) - 10px);
            height: 2px;
            background-color: rgb(175, 175, 175);
        }

        #myForm {
            margin-top: 10px;
            text-align: left;
            font-size: 20px;
            margin-left: 20px;
            line-height: 40px;
        }

        input.keywordInput {
            width: 150px;
        }

        select.categoryInput {
            width: 250px;
        }

        input.checkbox {
            margin-left: 20px;
        }

        input.shipping {
            margin-left: 36px;
        }

        #milesInput {
            width: 60px;
            margin-left: 25px;
        }

        #milesLabel {
            margin-left: 0px;
            color: grey;
        }

        #hereLabel {
            font-weight: normal;
            color: grey;
        }

        #zipRadio {
            margin-left: 408px;
            display: inline-block;
            vertical-align: top;
        }

        #zipInput {
            width: 150px;
            display: inline-block;
            vertical-align: top;
        }

        input[type=submit] {
            margin-left: 250px;
        }

        #pageSearch {
            margin-top:30px;
        }
        #divSearch {
            margin: auto;
            width: 1400px;
        }
        #pageDetail {
            margin:30px;
        }
        #divDetail {
            margin: auto;

        }

        #sellerButton {
            width: 300px;
            height: 100px;
            margin: auto;
        }

        #divSeller {
            margin: auto;
            width: 1040px;
            height: auto;
        }

        #iframeSeller {
            margin: 0px;
            padding: 0px;
            width: 1040px;
            height: 300px;

            overflow-x: hidden;
            border:none;
        }

        #similarButton {
            width: 300px;
            height: 100px;
            margin: auto;
        }

        #divSimilar {
            margin: auto;
            margin: auto;
            width: 1000px;
            overflow: auto;
            border: 2px solid rgb(200, 200, 200);

        }

        #searchTable {
            width: 1400px;
            border-collapse: collapse;
        }

        #detailTable {
            margin:auto;
            border-collapse: collapse;
            max-width:800px;
        }

        table, th, td {
            border: 2px solid rgb(200, 200, 200);
            font-size: 20px;
        }

        #divSimilar td {
            border: none;
            padding-left: 20px;
            padding-right: 20px;
        }

        #similarTable {
            border: none;
        }

        .detailRefrence {
            cursor: pointer;

        }

        .detailRefrence:hover {
            color: grey;

        }

    </style>
</head>

<body onload="loadPage()">

<div class="divForm">
    <div class="formTitle">
        <p class="formTitle">Product Search</p>
    </div>
    <div class="divideLine"></div>
    <form id="myForm" name="myForm" method="POST">
        <b>Keyword</b>
        <input id="keywordInput" class="keywordInput" type="text" name="keywords" maxlength="255" size="100"
               value="" required/>
        <input id="itemIdInput" class="itemIdInput" name="itemIdInput" type="text" maxlength="255" size="100"
               style="display:none" value="" disabled>
        <br/>
        <b>Category</b>
        <select class="categoryInput" name="category">
            <option id="All" value="All">All Categories</option>
            <option id="Art" value="Art">Art</option>
            <option id="Baby" value="Baby">Baby</option>
            <option id="Books" value="Books">Books</option>
            <option id="CSA" value="CSA">Clothing, Shoes & Accessories</option>
            <option id="CTN" value="CTN">Computer/Tablets & Networking</option>
            <option id="HB" value="HB">Health & Beauty</option>
            <option id="Music" value="Music">Music</option>
            <option id="VGC" value="VGC">Video Games & Consoles</option>
        </select>
        <br/>
        <b>Condition</b>
        <input id="condition1" class="checkbox" type="checkbox" name="condition1" value="New">New
        <input id="condition2" class="checkbox" type="checkbox" name="condition2" value="Used">Used
        <input id="condition3" class="checkbox" type="checkbox" name="condition3" value="Unspecified">Unspecified
        <br/>
        <b>Shipping Options</b>
        <input id="shipping1" class="shipping" type="checkbox" name="shipping1" value="LocalPickup">Local Pickup
        <input id="shipping2" class="shipping" type="checkbox" name="shipping2" value="FreeShipping">Free Shipping
        <br/>
        <input id="nearbySearch" class="nearbySearch" type="checkbox" name="nearbySearch" value="enabled"
               onchange="enableSearch(this)"><b>Enable Nearby Search</b>
        <input id="milesInput" type="text" name="distance" maxlength="255" size="100" placeholder="10" value=""
               disabled>
        <b id="milesLabel">miles from</b>
        <input id="hereRadio" type="radio" name="hereRadio" value="Here" checked disabled onclick="clickHere()">
        <id id="hereLabel">Here</id>
        <br>
        <input id="zipRadio" type="radio" name="zipRadio" value="" onclick="clickZip()" disabled>
        <input id="zipInput" type="text" name="zipInput" maxlength="5" placeholder="zip code" disabled required>
        <br/>
        <input id="submitButton" type="submit" value="Search" disabled>
        <button type="button" onclick="clearPage()">Clear</button>
    </form>
</div>

<div id="pageSearch" name="pageSearch" style="display:none">
    <div id="divSearch" name="divSearch"></div>
</div>

<div id="pageDetail" name="pageDetail" style="display:none">
    <div id="divDetail" name="divDetail"></div>
    <div id="sellerButton" name="sellerButton" style="text-align: center" data-checked="false">
        <p id="sellerButtonText" style="color:grey">click to show seller message</p>
        <img id="sellerButtonArrow" src="http://csci571.com/hw/hw6/images/arrow_down.png"
             style="width:40px; height:20px" onclick="clickSeller()">
    </div>
    <div id="divSeller" name="divSeller" style="display:none">
        <iframe id="iframeSeller" name="iframeSeller" onload="resizeIframe(this)"></iframe>
    </div>

    <div id="similarButton" name="similarButton" style="text-align: center"
         data-checked="false">
        <p id="similarButtonText" style="color:grey">click to show similar message</p>
        <img id="similarButtonArrow" src="http://csci571.com/hw/hw6/images/arrow_down.png"
             style="width:40px; height:20px" onclick="clickSimilar()">
    </div>
    <div id="divSimilar" name="divSimilar" style="display:none"></div>
</div>

<script type="text/javascript">
    function clearPage() {
        document.getElementById("keywordInput").setAttribute("value", "");

        document.getElementById("All").removeAttribute("selected");
        document.getElementById("Art").removeAttribute("selected");
        document.getElementById("Baby").removeAttribute("selected");
        document.getElementById("Books").removeAttribute("selected");
        document.getElementById("CSA").removeAttribute("selected");
        document.getElementById("CTN").removeAttribute("selected");
        document.getElementById("HB").removeAttribute("selected");
        document.getElementById("Music").removeAttribute("selected");
        document.getElementById("VGC").removeAttribute("selected");
        document.getElementById("All").setAttribute("selected", "true");

        document.getElementById("condition1").removeAttribute("checked");
        document.getElementById("condition2").removeAttribute("checked");
        document.getElementById("condition3").removeAttribute("checked");
        document.getElementById("shipping1").removeAttribute("checked");
        document.getElementById("shipping2").removeAttribute("checked");
        document.getElementById("nearbySearch").removeAttribute("checked");

        document.getElementById("milesInput").setAttribute("disabled", "disabled");
        document.getElementById("zipRadio").setAttribute("disabled", "disabled");
        document.getElementById("zipInput").setAttribute("disabled", "disabled");
        document.getElementById("hereRadio").setAttribute("disabled", "disabled");
        document.getElementById("milesLabel").setAttribute("style", "color: grey");
        document.getElementById("hereLabel").setAttribute("style", "color: grey");
        document.getElementById("milesInput").setAttribute("value", "");
        document.getElementById("hereRadio").checked = true;
        document.getElementById("zipRadio").removeAttribute("checked");
        document.getElementById("zipInput").setAttribute("value", "");

        document.getElementById("myForm").reset();

        document.getElementById("pageSearch").style.display = "none";
        document.getElementById("pageDetail").style.display = "none";
    }

    function loadForm() {
        document.getElementById("All").removeAttribute("selected");
        document.getElementById("Art").removeAttribute("selected");
        document.getElementById("Baby").removeAttribute("selected");
        document.getElementById("Books").removeAttribute("selected");
        document.getElementById("CSA").removeAttribute("selected");
        document.getElementById("CTN").removeAttribute("selected");
        document.getElementById("HB").removeAttribute("selected");
        document.getElementById("Music").removeAttribute("selected");
        document.getElementById("VGC").removeAttribute("selected");
        if (<?php if (isset($form["category"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("<?php if (isset($form["category"])) {
                echo $form["category"];
            } else {
                echo "";
            }?>").setAttribute("selected", "true");
        } else {
            document.getElementById("All").setAttribute("selected", "true");
        }
        if (<?php if (isset($form["keywords"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("keywordInput").setAttribute("value", "<?php if (isset($form["keywords"])) {
                echo $form["keywords"];
            } else {
                echo "";
            }?>");
        }
        if (<?php if (isset($form["condition1"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("condition1").setAttribute("checked", "true");
        }
        if (<?php if (isset($form["condition2"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("condition2").setAttribute("checked", "true");
        }
        if (<?php if (isset($form["condition3"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("condition3").setAttribute("checked", "true");
        }
        if (<?php if (isset($form["shipping1"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("shipping1").setAttribute("checked", "true");
        }
        if (<?php if (isset($form["shipping2"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("shipping2").setAttribute("checked", "true");
        }
        if (<?php if (isset($form["nearbySearch"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("nearbySearch").setAttribute("checked", "true");
            enableSearch(document.getElementById("nearbySearch"));
        }
        if (<?php if (isset($_POST["distance"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("milesInput").setAttribute("value", "<?php if (isset($_POST["distance"]) && $_POST["distance"] != '') {
                echo $_POST["distance"];
            } else {
                echo '';
            }?>");
        }
        if (<?php if (isset($form["hereRadio"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("hereRadio").setAttribute("checked", "true");
            document.getElementById("zipRadio").checked = false;
        }
        if (<?php if (isset($form["zipRadio"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("zipRadio").setAttribute("checked", "true");
            document.getElementById("zipInput").removeAttribute("disabled");
            document.getElementById("hereRadio").checked = false;
        }
        if (<?php if (isset($form["zipInput"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            document.getElementById("zipInput").setAttribute("value", "<?php if (isset($form["zipInput"])) {
                echo $form["zipInput"];
            } else {
                echo '';
            }?>");
        }
    }

    function enableSearch(checkbox) {
        if (checkbox.checked == true) {
            document.getElementById("milesInput").removeAttribute("disabled");
            document.getElementById("zipRadio").removeAttribute("disabled");
            document.getElementById("hereRadio").removeAttribute("disabled");
            document.getElementById("milesLabel").setAttribute("style", "color: black");
            document.getElementById("hereLabel").setAttribute("style", "color: black");
            if (document.getElementById("zipRadio").checked == true) {
                document.getElementById("zipInput").removeAttribute("disabled");
            } else {
                document.getElementById("zipInput").setAttribute("disabled", "disabled");
            }
        } else {
            document.getElementById("milesInput").setAttribute("disabled", "disabled");
            document.getElementById("zipRadio").setAttribute("disabled", "disabled");
            document.getElementById("zipInput").setAttribute("disabled", "disabled");
            document.getElementById("hereRadio").setAttribute("disabled", "disabled");
            document.getElementById("milesLabel").setAttribute("style", "color: grey");
            document.getElementById("hereLabel").setAttribute("style", "color: grey");

            document.getElementById("milesInput").setAttribute("value", "");
            document.getElementById("hereRadio").checked = true;
            document.getElementById("zipRadio").checked = false;
            document.getElementById("zipInput").setAttribute("value", "");
        }
    }

    function clickHere() {
        document.getElementById("zipInput").setAttribute("disabled", "disabled");
        document.getElementById("hereRadio").setAttribute("checked", "true");
        document.getElementById("zipRadio").checked = false;
        document.getElementById("zipInput").setAttribute("value", "");
    }

    function clickZip() {
        document.getElementById("zipInput").removeAttribute("disabled");
        document.getElementById("zipRadio").setAttribute("checked", "true");
        document.getElementById("hereRadio").checked = false;
    }

    function clickDetail(string) {
        document.getElementById("itemIdInput").disabled = false;
        document.getElementById("itemIdInput").value = string;
        document.getElementById("myForm").submit();
    }

    function clickSeller() {
        if (document.getElementById("sellerButton").getAttribute("data-checked") == "false") {
            showSeller();
            hideSimilar();
        } else {
            hideSeller();
        }
    }

    function clickSimilar() {
        if (document.getElementById("similarButton").getAttribute("data-checked") == "false") {
            showSimilar();
            hideSeller();
        } else {
            hideSimilar();
        }
    }

    function showSeller() {
        document.getElementById("sellerButton").setAttribute("data-checked", "true");
        document.getElementById("sellerButtonText").innerHTML = "click to hide seller message";
        document.getElementById("sellerButtonArrow").src = "http://csci571.com/hw/hw6/images/arrow_up.png";
        document.getElementById("divSeller").style.display = "";
    }

    function hideSeller() {
        document.getElementById("sellerButton").setAttribute("data-checked", "false");
        document.getElementById("sellerButtonText").innerHTML = "click to show seller message";
        document.getElementById("sellerButtonArrow").src = "http://csci571.com/hw/hw6/images/arrow_down.png";
        document.getElementById("divSeller").style.display = "none";
    }

    function showSimilar() {
        document.getElementById("similarButton").setAttribute("data-checked", "true");
        document.getElementById("similarButtonText").innerHTML = "click to hide similar message";
        document.getElementById("similarButtonArrow").src = "http://csci571.com/hw/hw6/images/arrow_up.png";
        document.getElementById("divSimilar").style.display = "";
    }

    function hideSimilar() {
        document.getElementById("similarButton").setAttribute("data-checked", "false");
        document.getElementById("similarButtonText").innerHTML = "click to show similar message";
        document.getElementById("similarButtonArrow").src = "http://csci571.com/hw/hw6/images/arrow_down.png";
        document.getElementById("divSimilar").style.display = "none";
    }

    function getLocation() {
        var s = document.createElement("script");
        s.src = "http://ip-api.com/json/?callback=setLocation";
        document.body.appendChild(s);
    }

    function setLocation(json) {
        var zip = json.zip
        document.getElementById("submitButton").removeAttribute("disabled");
        document.getElementById("hereRadio").setAttribute("value", zip);
    }

    function drawSearch() {
        if (<?php if(isset($itemJSON)) {echo "true";} else {echo "false";} ?>) {
            itemJSON = <?php if(isset($itemJSON)) {echo $itemJSON;} else {echo "null";} ?>;
            document.getElementById("divSearch").innerHTML = generateSearchHTML(itemJSON);
        }
    }

    function drawDetail() {
        if (<?php if(isset($detailJSON)) {echo "true";} else {echo "false";} ?>) {
            detailJSON = <?php if(isset($detailJSON)) {echo $detailJSON;} else {echo "null";} ?>;
            sellerJSON = <?php if(isset($sellerJSON)) {echo $sellerJSON;} else {echo "null";} ?>;
            similarJSON = <?php if(isset($similarJSON)) {echo $similarJSON;} else {echo "null";} ?>;
            document.getElementById("divDetail").innerHTML = generateDetailHTML(detailJSON);
            generateSellerHTML(sellerJSON);
            document.getElementById("divSimilar").innerHTML = generateSimilarHTML(similarJSON);
        }
    }

    function resizeIframe(obj) {
        document.getElementById("divSeller").style.display="";

        obj.style.height = obj.contentWindow.document.body.scrollHeight + 48 + 'px';
        //obj.style.width = obj.contentWindow.document.body.scrollWidth + 48 + 'px';
        //obj.style.height = obj.contentWindow.getComputedStyle(obj.contentDocument.documentElement).height;
        //obj.style.width = obj.contentWindow.getComputedStyle(obj.contentDocument.documentElement).width;
        document.getElementById("divSeller").style.height = document.getElementById("iframeSeller").style.height;
        document.getElementById("divSeller").style.width = document.getElementById("iframeSeller").style.width;

        document.getElementById("divSeller").style.display="none";

    }

    function generateSearchHTML(jsonObj) {

        if (jsonObj.Ack == 'Failure') {
            search_text = "<div style='margin:auto;width:1000px;background-color:rgb(240,240,240);border:2px solid rgb(220,220,220)'>";
            search_text += "<p style='margin:0px;text-align:center;font-size:22px;'>" + jsonObj.ErrorMessage + "</p>";
            search_text += "</div>";
            return search_text;
        }
        if (jsonObj.Item.length == 0) {
            search_text = "<div style='margin:auto;width:1000px;background-color:rgb(240,240,240);border:2px solid rgb(220,220,220)'>";
            search_text += "<p style='margin:0px;text-align:center;font-size:22px;'>No Records has been found</p>";
            search_text += "</div>";
            return search_text;
        }

        root = jsonObj.DocumentElement;
        search_text = "<html><head><title></title></head><body style='font-family:Times New Roman'>";
        search_text += "<table id='searchTable' >";
        search_text += "<tbody>";
        search_text += "<tr>";

        var searchHeader = jsonObj.Header;
        for (i = 0; i < searchHeader.length; i++) {
            search_text += "<th nowrap>" + searchHeader[i] + "</th>";
        }
        search_text += "</tr>";

        var search_items = jsonObj.Item;
        for (i = 0; i < search_items.length; i++)
        {
            search_item = search_items[i];
            search_text += "<tr>";
            search_item_keys = Object.keys(search_item);
            for (j = 0; j < search_item_keys.length; j++) {
                key = search_item_keys[j];
                if (key == 'Price') {
                    search_text += "<td>"

                        search_text += '$';

                    search_text += search_item[key].Value + "</td>";
                } else if (search_item_keys[j] == "Photo") {
                    if (search_item[key] == "N/A") {
                        search_text += "<td>" + "</td>";
                    } else {
                        search_text += "<td style='width:100px'><img style='width:100px;max-height:150px;' src='" + search_item[key] + "'></td>";
                    }
                } else if (search_item_keys[j] == "ItemId") {
                    continue;
                } else if (search_item_keys[j] == "Name") {
                    search_text += "<td><p class=detailRefrence onclick=clickDetail(" + search_item["ItemId"] + ")>" + search_item["Name"] + "</p></td>";
                } else {
                    search_text += "<td>" + search_item[key] + "</td>";
                }

            }
            search_text += "</tr>";
        }
        search_text += "</tbody>";
        search_text += "</table>";
        search_text += "</body></html>";
        return search_text;
    }

    function generateDetailHTML(jsonObj) {
        if (jsonObj.Ack == 'Failure') {
            detail_text = "<div style='margin:auto;width:1000px;background-color:rgb(240,240,240);border:2px solid rgb(220,220,220)'>";
            detail_text += "<p style='margin:0px;text-align:center;font-size:22px;'><b>No Item Detail has been Found due to Invalid or Non-Existent Item ID</b></p>";
            detail_text += "</div>";
            return detail_text ;
        }
        root = jsonObj.DocumentElement;
        detail_text = "<html><head><title></title></head><body style='font-family:Times New Roman'>";
        detail_text = "<H1 style='text-align:center;margin:auto;font-size:40px'>Item Details</H1>";
        detail_text += "<table id='detailTable'>";
        detail_text += "<tbody>";

        for (i = 0; i < jsonObj.length; i++)
        {
            detail = jsonObj[i];

            detail_keys = Object.keys(detail);
            for (j = 0; j < detail_keys.length; j++) {
                key = detail_keys[j];
                if (key == 'ItemSpecifics') {
                    for (k = 0; k < detail.ItemSpecifics.length; k++) {
                        detail_text += "<tr>";
                        detail_text += "<td><b>" + detail.ItemSpecifics[k].Name + "</b></td>";
                        detail_text += "<td>" + detail.ItemSpecifics[k].Value[0] + "</td>";
                        detail_text += "</tr>";
                    }
                } else if (key == 'Photo') {
                    if (detail[key] == "N/A") {
                        detail_text += "<tr>";
                        detail_text += "<td><b>" + key + "</b></td>";
                        detail_text += "<td>" + "</td>";
                        detail_text += "</tr>";
                    } else {
                        detail_text += "<tr>";
                        detail_text += "<td><b>" + key + "</b></td>";
                        detail_text += "<td><img src='" + detail[key] + "' style='height:300px;max-width:600px'></td>";
                        detail_text += "</tr>";
                    }
                } else if (key == 'Location') {
                    if (detail['Location'] != 'N/A' && detail['PostalCode'] != 'N/A') {
                        detail_text += "<tr>";
                        detail_text += "<td><b>" + "Location" + "</b></td>";
                        detail_text += "<td>" + detail['Location'] + ", " + detail['PostalCode'] + "</td>";
                        detail_text += "</tr>";
                    }
                    if (detail['Location'] != 'N/A' && detail['PostalCode'] == 'N/A') {
                        detail_text += "<tr>";
                        detail_text += "<td><b>" + "Location" + "</b></td>";
                        detail_text += "<td>" + detail['Location'] + "</td>";
                        detail_text += "</tr>";
                    }
                } else if (key == 'PostalCode'){

                } else {
                    if (detail[key] != 'N/A') {
                        detail_text += "<tr>";
                        detail_text += "<td><b>" + key + "</b></td>";
                        detail_text += "<td>" + detail[key] + "</td>";
                        detail_text += "</tr>";
                    }
                }
            }
        }
        detail_text += "</tbody>";
        detail_text += "</table>";
        detail_text += "</body></html>";
        return detail_text;
    }

    function generateSellerHTML(jsonObj) {
        if (jsonObj == '') {
            seller_text = "<div style='margin:auto;width:1000px;background-color:rgb(240,240,240);border:2px solid rgb(220,220,220)'>";
            seller_text += "<p style='margin:0px;text-align:center;font-size:22px;'><b>No Seller Message Found</b></p>";
            seller_text += "</div>";
            document.getElementById("divSeller").innerHTML=seller_text;
        } else {
            document.getElementById("iframeSeller").srcdoc = jsonObj.Description;
        }
        return;
    }

    function generateSimilarHTML(jsonObj) {
        root = jsonObj.DocumentElement;
        if (jsonObj.length == 0) {
            similar_text = "<div style='margin:auto;width:996px;background-color:rgb(240,240,240);border:2px solid rgb(220,220,220)'>";
            similar_text += "<p style='margin:0px;text-align:center;font-size:22px;'><b>No Similar Items Found</b></p>";
            similar_text += "</div>";
            return similar_text;
        }
        similar_text = "<html><head><title></title></head><body style='font-family:Times New Roman'>";
        similar_text += "<table id='similarTable'>";
        similar_text += "<tbody>";

        similar_text += "<tr>";
        for (i = 0; i < jsonObj.length; i++)
        {
            similar = jsonObj[i];

            similar_text += "<td>";

            if (similar['Photo'] != 'N/A') {
                similar_text += "<img src='" + similar['Photo'] + "' style='width:200px; max-height:300px;' class=detailRefrence onclick=clickDetail(" + similar["ItemID"] + ")><br>";
            }
            if (similar['Title'] != 'N/A') {
                similar_text += "<p class=detailRefrence onclick=clickDetail(" + similar["ItemID"] + ")>" + similar['Title'] + "</p>";
            }
            similar_text += "</td>";

        }
        similar_text += "</tr>";
        similar_text += "<tr>";
        for (i = 0; i < jsonObj.length; i++)
        {
            similar = jsonObj[i];

            similar_text += "<td style='text-align:center'><b>";
            similar_text += similar['Price'];
            similar_text += "</b></td>";

        }
        similar_text += "</tr>";
        similar_text += "</tbody>";
        similar_text += "</table>";
        similar_text += "</body></html>";
        return similar_text;
    }

    function loadPage() {
        getLocation();
        loadForm();
        if (<?php if (isset($form["itemIdInput"])) {
            echo "true";
        } else {
            echo "false";
        } ?>) {
            drawDetail();
            document.getElementById("pageDetail").style.display = "";
        } else {
            drawSearch();
            document.getElementById("pageSearch").style.display = "";
        }
    }
</script>
<!-- yyu726 Version 3.0 -->
</body>

</html>