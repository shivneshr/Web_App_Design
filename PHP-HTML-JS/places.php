<?php
  #global $Search,$SearchResult;
  $Search = 0;
  $SearchResult = NULL;
  $Details =0;
  $Number =0;
  $DetailsResult = NULL;

  switch($_SERVER['REQUEST_METHOD']){
    case 'GET':
    break;
    case 'POST':
        if($_POST['placesAPI']=="TRUE"){
          callPlacesAPI();
        }
        else if($_POST['detailsAPI']=="TRUE")
        {
          callDetailsAPI();
        }
    break;
    default:
  }

  function getGeoLocation($locationName){

    // API Reference
    /*https://maps.googleapis.com/maps/api/geocode/json?
    address=1600+Amphitheatre+Parkway,+Mountain+View,+CA&
    key=YOUR_API_KEY
    */

    $parameter = array();
    $parameter["address"] = $locationName;
    $parameter["key"] = "AIzaSyDcddz4rqeqdCVb2Yl7_bqKqDm8lslU1OA";
    $url="https://maps.googleapis.com/maps/api/geocode/json?" . http_build_query($parameter);

    // Making a call to the Google geocode API
    $result = json_decode(file_get_contents($url),true);

    $geo = $result['results'][0]['geometry'];
    $lat = $geo['location']['lat'];
    $lng = $geo['location']['lng'];
    $_POST['latitude'] = $lat;
    $_POST['longitude'] = $lng;

    return $lat . ',' . $lng;
  }

  function callDetailsAPI(){

    $googleDetailsHost='https://maps.googleapis.com/maps/api/place/details/json?';
    $googlePhotosHost = 'https://maps.googleapis.com/maps/api/place/photo?';

    $googleDetailsArray = array();
    $googlePhotosArray = array();

    $googleDetailsArray["place_id"]=$_POST["place_id"];
    $googleDetailsArray["key"]="AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us";

    $results = file_get_contents($googleDetailsHost . http_build_query($googleDetailsArray));

    $result = json_decode($results);
    $result = $result->result;

    $photos = $result->photos;
    $reviews = $result->reviews;

    $ctr=0;
    foreach($photos as $key => $value)
    {
      if($ctr>4){
        break;
      }

      $googlePhotosArray["maxwidth"]=850;
      $googlePhotosArray["photoreference"] = $value->photo_reference;
      $googlePhotosArray["key"] = "AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us";

      $imageData = file_get_contents($googlePhotosHost . http_build_query($googlePhotosArray));
      file_put_contents("image".$ctr.".png",$imageData);
      //echo $value->photo_reference . "\n" . $value->height . "\n" . $value->width . "\n";

      $ctr +=1;
    }

    global $Search, $SearchResult, $Details, $DetailsResult,$Number;
    $Search = 0;
    $SearchResult = NULL;
    $Details = 1;
    $Number = $ctr;
    $DetailsResult = $reviews;

  }

  function callPlacesAPI(){
    /*
    API Reference:

    https://maps.googleapis.com/maps/api/place/nearbysearch/json?
    location=34.0266,-118.2831&
    radius=500&
    type=restaurant&
    key=AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us
    */

    $googlePlacesHost = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?';
    $googlePlacesArray = array();

    // If user does not provide lat & lng use geoCoding API to get them
    if($_POST["from"]=="here")
      $googlePlaceArray["location"] = $_POST["latitude"].','.$_POST["longitude"];
    else
      $googlePlaceArray["location"] = getGeoLocation($_POST["location"]);

    // Default radius is 10 Miles
    if($_POST["distance"]!="")
      $googlePlaceArray["radius"] =  (string)(1609.34 * (float)$_POST["distance"]);
    else
      $googlePlaceArray["radius"] = (string)(1609.34 * 10);

    // If the default option is selcted then ignore it
    if($_POST["types"]!="default")
      $googlePlaceArray["types"] = $_POST["types"];

    // This is a mandatory field
    $googlePlaceArray["keyword"] = $_POST["keyword"];
    $googlePlaceArray["key"] = "AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us";

    $result = file_get_contents($googlePlacesHost . http_build_query($googlePlaceArray));

    global $Search, $SearchResult, $Details, $DetailsResult;
    $Search = 1;
    $SearchResult = $result;
    $Details =0;
    $DetailsResult = NULL;

  }
?>

<!DOCTYPE html>
<html>
<head>
  <title></title>

    <style type="text/css">

      #searchBar{
        background-color: #F1F1F1;
        padding: 5px 5px 5px 5px;
        height: 300px;
        width: 50%;
      }

      h2{
        padding: 0 0 0 0;
      }

      body{
        width: 100%;

      }

      table {
        margin: auto;
        border-collapse: collapse;
      }

      form {
        text-align: left;
      }

      #mapContainer {
        position: absolute;
        visibility: hidden;
        height: 300px;
        width: 400px;
      }

      #modes{
        position: absolute;
        z-index: 100;
        visibility: hidden;
      }

      #modes.input {
            background-color: #e7e7e7; /* Green */
            border: none;
            color: black;
            padding: 5px 15px;
            text-align: center;
            text-decoration: none;
            display: inline-block;
            font-size: 11px;
        }

    </style>


    <?php
    if($_SERVER['REQUEST_METHOD'] === 'POST'){
    echo "<script async defer src='https://maps.googleapis.com/maps/api/js?key=AIzaSyBqcIlGrq28X7vTB0_5RegUuvZ6JwwOU8k&callback=initMap'></script>";
    }
    ?>


    <script type="text/javascript">

      var gpsResponse= "";
      var Origin={};
      var Destination={};
      var reviewData="";
      var reviewPhoto = "";
      var closedArrow = "http://cs-server.usc.edu:45678/hw/hw6/images/arrow_down.png";
      var openArrow = "http://cs-server.usc.edu:45678/hw/hw6/images/arrow_up.png";

      function renderMap(e){
        initMap();

        Destination.lat = parseFloat(e.getAttribute("lat"));
        Destination.lng = parseFloat(e.getAttribute("lng"));

        mapdiv = document.getElementById("mapContainer");
        modes = document.getElementById("modes");
        if(mapdiv.style.visibility === "hidden"){
          var parentPosition = getPosition(e);
          var xPosition = parentPosition.left;
          var yPosition = parentPosition.top+20;

          mapdiv.style.left = xPosition + "px";
          modes.style.left = xPosition+5 + "px"
          mapdiv.style.top = yPosition + "px";
          modes.style.top = yPosition+5 + "px";
          mapdiv.style.visibility = "visible";
          modes.style.visibility = "visible";
        }else{
          mapdiv.style.visibility = "hidden";
          modes.style.visibility = "hidden";
        }
      }

      function calcRoute(travelMode) {
        var request = {
          origin: new google.maps.LatLng(Origin, true),
          destination: new google.maps.LatLng(Destination, true),
          travelMode: travelMode
        };
        directionsService.route(request, function (response, status) {
          if (status == 'OK') {
            console.log(response);
            directionsDisplay.setDirections(response);
          }
        });
      }

      // Helper function to get an element's exact position

      function getPosition(el) {
        el = el.getBoundingClientRect();
        return {
            left: el.left + window.scrollX,
            top: el.top + window.scrollY
        }
      }

      function initMap() {

        directionsService = new google.maps.DirectionsService();
        directionsDisplay = new google.maps.DirectionsRenderer();

        <?php

          if($_SERVER['REQUEST_METHOD'] === 'POST')
          {
            echo "Origin.lat = parseFloat(". $_POST["latitude"] . ");";
            echo "Origin.lng = parseFloat(". $_POST["longitude"] . ");";
          }

         ?>
        map = new google.maps.Map(document.getElementById("map"), {
            zoom: 15,
            center: Origin
        });

        var marker = new google.maps.Marker({
            position: Origin,
            map: map
        });

        google.maps.event.trigger(map, 'resize');
        directionsDisplay.setMap(map);
      }

      function getGPSLocation()
      {
        var request = new XMLHttpRequest();
        var url = 'http://freegeoip.net/json/';

        request.onreadystatechange = function()
        {
          if (this.readyState === 4 && this.status === 200)
          {
            var response = JSON.parse(this.responseText);

            //alert(response['latitude']);
            //Set the latitude and longitudefor the Here choice
            document.getElementById('latitude').value = response['latitude'];
            document.getElementById('longitude').value = response['longitude'];
            document.getElementById('search_btn').disabled = false;
          }
        }

        request.open("GET", url, true);
        request.send();
      }

      function postToDetails(place_id,record_name){
        document.getElementById('placesAPI').value = "FALSE";
        document.getElementById('detailsAPI').value = "TRUE";
        document.getElementById('place_id').value = place_id;
        document.getElementById('place_name').value = record_name;
        document.getElementById('search').submit();
      }

      function postToPlaces(){
        document.getElementById('placesAPI').value = "TRUE";
        document.getElementById('detailsAPI').value = "FALSE";
        document.getElementById('search').submit();
      }

      function displaySearchResults(searchResults)
      {
        var tableData = "<tr><th>Category</th><th>Name</th><th>Address</th></tr>";

        var jsonObj = JSON.parse(searchResults);
        var results = jsonObj["results"];

        if(results.length!=0)
        {

        for(var item in results){
          var record = results[item];
          //console.log(JSON.stringify(record));
          tableData += `
            <tr>
              <td><img src="` + record['icon'] + `" width="40px" height="100%" /></td>
              <td><a href='javascript:postToDetails("`+record["place_id"]+`","`+record["name"]+`")'>` + record["name"] + `</a></td>
              <td><a onclick="renderMap(this)" lat="`+record["geometry"]["location"]["lat"]+`" lng="`+record["geometry"]["location"]["lng"]+`">` + record["vicinity"] + `</a></td>
            </tr>
          `;
          document.getElementById('placesTable').innerHTML = tableData;
        }
      } else{
        document.getElementById("placesData").innerHTML = "<p>No records have been found!</p>"
      }


      }

      function displayReviewsResults(reviewsResults, number)
      {
        document.getElementById('reviewData').style.display ="block";
        document.getElementById('photoData').style.display = "block";

        var jsonObj = reviewsResults;

        document.getElementById('reviewArrow').setAttribute("src",closedArrow);
        document.getElementById('photoArrow').setAttribute("src",closedArrow);

        if(jsonObj.length!=0)
        {
        for(var item in jsonObj){
          var record = jsonObj[item];
          //console.log(JSON.stringify(record));
          reviewData += `
            <tr>
              <td style="text-align:center;">
                <img src="` + record['profile_photo_url'] + `" width='30px' height='30px' />
                `+record['author_name']+`
              </td>
            </tr>
            <tr>
              <td>
                `+record['text']+`
              </td>
            </tr>
          `;
        }
      }else{
        reviewData="<p>No reviews found !</p>";
      }

        if(number == 0){
          reviewPhoto = "<p>No photos found !</p>";
        }
        for (i = 0; i < number; i++) {
          reviewPhoto += "<tr><td><a href='image"+i+".png' target='_blank'><img src='image"+i+".png'></a></td></tr>";
        }

        document.getElementById("headingName").innerHTML = `<p>
          <b>`+document.getElementById('place_name').value+`</b>
        </p>`;
      }

      function changeReviews(){
        var src = document.getElementById('reviewArrow').getAttribute("src");

        if(src===closedArrow){
          document.getElementById('reviewArrow').setAttribute("src",openArrow);
          document.getElementById('reviewsTable').innerHTML = reviewData;
          document.getElementById('photoArrow').setAttribute("src",closedArrow);
          document.getElementById('photosTable').innerHTML = "";
        }
        else{
          document.getElementById('reviewArrow').setAttribute("src",closedArrow);
          document.getElementById('reviewsTable').innerHTML = "";
        }
      }

      function changePhotos(){
        var src = document.getElementById('photoArrow').getAttribute("src");

        if(src === closedArrow){
          document.getElementById('photoArrow').setAttribute("src",openArrow);
          document.getElementById('photosTable').innerHTML = reviewPhoto;
          document.getElementById('reviewArrow').setAttribute("src",closedArrow);
          document.getElementById('reviewsTable').innerHTML = "";
        }
        else{
          document.getElementById('photoArrow').setAttribute("src",closedArrow);
          document.getElementById('photosTable').innerHTML = "";
        }
      }

      function clearArea(){
        document.getElementById('keyword').value ="";
        document.getElementById('category').value ="";
        document.getElementById('distance').value ="";
        document.getElementById('place_id').value ="";
        document.getElementById('place_name').value ="";
        document.getElementById('location').value ="";

        document.getElementById('placesTable').innerHTML="";
        document.getElementById('map').innerHTML ="";
        document.getElementById('reviewsTable').innerHTML="";
        document.getElementById('photosTable').innerHTML="";
      }

      // All the intialization on-load goes here
      function initialize()
      {
        var lat = document.getElementById('latitude').val;

        document.getElementById("mapContainer").visibility = "hidden";
        document.getElementById("modes").visibility="hidden";
        document.getElementById('reviewData').style.display="none";
        document.getElementById('photoData').style.display = "none";

        var number = <?php echo $Number; ?>;
        if(lat == "")
        {
          document.getElementById('search_btn').disabled = true;
        }

        // initialize the existing PHP post variables
        <?php

        if($_SERVER['REQUEST_METHOD'] === 'POST'){
          echo "document.getElementById('keyword').value ='" . $_POST["keyword"] ."';";
          echo "document.getElementById('category').value ='" . $_POST["types"] . "';";
          echo "document.getElementById('distance').value ='" . $_POST["distance"] ."';";
          echo "document.forms['search']['" . $_POST["from"] . "'].checked=true;";
          echo "document.getElementById('latitude').value ='" . $_POST["latitude"] ."';";
          echo "document.getElementById('longitude').value ='" . $_POST["longitude"] ."';";
          echo "document.getElementById('place_id').value ='" . $_POST["place_id"] ."';";
          echo "document.getElementById('place_name').value ='" . $_POST["place_name"] ."';";

          if(isset($_POST["location"])){
            echo "document.getElementById('location').value ='" . $_POST["location"] ."';";
          }
        }
        else{
          // Call the getGPSLocation to obtain the latitude and longitude
            echo "getGPSLocation();";
        }
        ?>

        <?php
          if($Search == 1)
          {
            echo "displaySearchResults(" . json_encode($SearchResult) . ");";
          }else if($Details ==1){
            echo "displayReviewsResults(" . json_encode($DetailsResult).",number);";
          }
        ?>
      }

    </script>
</head>
<body onload="initialize()">
  <center>
    <div id="searchBar">

      <h2><i>Travel and Entertainment Search</i></h2>
      <hr />
      <form name="search" id="search" action=<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?> method="post">

        <b>Keyword </b> <input name="keyword" type="text" id="keyword" required/><br />
        <b>Category </b>

        <select name="types" id="category">
          <option selected>default</option>
          <option>cafe</option>
          <option>bakery</option>
          <option>restaurant</option>
          <option>beauty salon</option>
          <option>casino</option>
          <option>movie theater</option>
          <option>lodging</option>
          <option>airport</option>
          <option>train station</option>
          <option>subway station</option>
          <option>bus station</option>
        </select><br />

        <b>Distance (miles) </b><input type="number" placeholder="10" name="distance" id="distance" />
        <b>from </b><input type="radio" name="from" id="here" value="here" checked> Here<br />
        <input type="radio" name="from" id="user_input" value="user_input"/><input type="text" placeholder="location" name="location" id="location" disabled="disabled"/><br />
        <input type="submit" onclick="postToPlaces()" value="Search" id="search_btn" name="search_btn" class="button"/>
        <input type="reset" onclick="clearArea()" value="Clear" />
        <input type="hidden" name="latitude" id="latitude" />
        <input type="hidden" name="longitude" id="longitude" />
        <input type="hidden" name="place_id" id="place_id"/>
        <input type="hidden" name="place_name" id="place_name" />
        <input type="hidden" name="placesAPI" id="placesAPI" />
        <input type="hidden" name="detailsAPI" id="detailsAPI" />
      </form>

    </div>
    <div id="placesData" style="margin-top: 10px;">
      <table id="placesTable" border="1" style="width:60%;">
      </table>
    </div>

    <div id="mapContainer">
      <div id="map" style="height:300px; width:400px;">
      </div>
    </div>

    <div id="modes">
      <input type="button" id="walkBtn" class="button" value="Walk there" onclick="calcRoute('WALKING');" /><br />
      <input type="button" id="bicycleBtn" class="button" value="Bike there" onclick="calcRoute('BICYCLING');" /><br />
      <input type="button" id="driveBtn" class="button" value="Drive there" onclick="calcRoute('DRIVING');" /><br />
    </div>

    <div id="headingName">
    </div>
    <div id="reviewData">
      <p>click to show reviews</p>
      <a onclick="changeReviews()"><img id="reviewArrow" src="" style="width:40px;height:20px;"/></a>
      <table id="reviewsTable" border="1" style="width:50%;">

      </table>
    </div>

    <div id="photoData">
      <p>click to show photos</p>
      <a onclick="changePhotos()"><img id="photoArrow" src="" style="width:40px;height:20px;"/></a>
      <table id="photosTable" border="1" style="width:50%;">

      </table>
    </div>

  <!--  <div id="result">
        <table>
          <?php

            if($_SERVER['REQUEST_METHOD'] == 'POST')
            {
              echo "Hello";
              foreach ($_POST as $key => $value) {
              echo "<tr>";
              echo "<td>";
              echo $key;
              echo "</td>";
              echo "<td>";
              echo $value;
              echo "</td>";
              echo "</tr>";
            }

              echo http_build_query($_POST) . "\n";
            }

        ?>
      </table>
    </div>-->
  </center>
  <script type="text/javascript">

  document.search.from[1].onchange = function () {
    checkFun();
  };

  function checkFun() {
    if (document.search.from[1].checked) {
      document.search.location.disabled = false;
      document.search.location.required = "required";
    }
    else {
      document.search.location.disabled = true;
      document.search.location.required = "";
    }
  }

  document.search.from[0].onchange = function () {
    checkFun1()
  };

  function checkFun1() {
    if (document.search.from[0].checked) {
      document.search.location.disabled = true;
      document.search.location.required = "";
    }
  }
  </script>
  </body>
</html>
