
// Express.js framework for REST API's
var express = require('express')
var cors = require('cors')

// Request library for making remote HTTP calls
var request = require('request');

var sleep = require('system-sleep');

// Used to parse the body of the request
const bodyParser = require('body-parser');

// Helper libraries for creating remote calls
const { URL } = require('url');
const querystring = require('querystring');
const http = require('http');

// Creating the express Applications
var app = express()

// Specification of library usage
app.use(bodyParser.json()); // support json encoded bodies
app.use(bodyParser.urlencoded({ extended: true })); // support encoded bodies
app.use(cors())


// Request and Response objects
var apiResponse = {statusCode:0,data:"",error:""};


function createResponse(data, error, statusCode){
  apiResponse.data= data;
  apiResponse.error = error;
  apiResponse.statusCode=statusCode;
}


// REST calls with ROUTES mentioned in each function

app.get('/', function (req, res) {
  res.send('Travel and Entertainment Application')
})


app.post('/yelpreviews',function (req,res)
{
  var host ="https://api.yelp.com/v3/businesses/matches/best?";
  var reviewsUrl =host+querystring.stringify(req.body);
  var yelpKey="254UzWQzQ2KZf6Tun87rTfdm5fOO2F7EDJP2bYjDh-rmEq65kTFEzZrqvX8hFo3qopYwxTcSIMidl4GtvTEYIQ4Hwam-CREum4qaJ4uh-3JVb2CKI05wueNET_DOWnYx";

  console.log(reviewsUrl)
  var properties ={
      url : reviewsUrl,
      headers : {
          'Authorization' : 'Bearer '+yelpKey
      }
  };

  request(properties,function(error,response,body){
     var result= JSON.parse(response.body);
     var reviews = [];
     if(result.businesses.length === 1){
         properties.url = "https://api.yelp.com/v3/businesses/"+result.businesses[0].id+"/reviews";
         request(properties,function(err,resp,content){
            var reviewResult = JSON.parse(resp.body);
            if(reviewResult.total <= 5){
               reviews = reviewResult.reviews;
            }else{
                reviews = reviewResult.reviews.splice(0,5);
            }
            console.log(JSON.stringify(reviews));
            res.send(reviews);
         });
     }else{
          console.log(JSON.stringify(reviews));
           res.send(reviews);
     }
  })
});


function makeRemoteCalls_GooglePlaces(url,place_list,callback){

  var host = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?";

  console.log(url);

  request(url, function (error,response,body) {

    if (!error && response.statusCode == 200) {
        body = JSON.parse(body);
        var places = body.results;

        for(var key in places){
          var place = places[key];
          place_list.push(
            {
              Category : place.icon,
              Name : place.name,
              Address : place.vicinity,
              place_id : place.place_id,
              latitude : place.geometry.location.lat,
              longitude : place.geometry.location.lng
            }
          );
        }

        console.log("places " + place_list.length);

        if(body.next_page_token!==undefined){
          console.log("Here inside");

          var obj={pagetoken:body.next_page_token,key:"AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us"};
          var placeURL = host+querystring.stringify(obj);
          console.log(placeURL);
          sleep(2000);
            makeRemoteCalls_GooglePlaces(placeURL,place_list,function(response){
              console.log("Internal:" + response);
              callback(place_list);
          });
        }else{
          callback(place_list);
        }
      }else{
        callback();
      }
    });

}


app.post('/places_new',function(req,res){

  var host = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?";
  req.body.key = "AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us";

  console.log(JSON.stringify(req.body));
  var placeURL = host+querystring.stringify(req.body);
  var place_list=[];
  makeRemoteCalls_GooglePlaces(placeURL,place_list,function(response){
    console.log("In the main function call back");
    console.log(JSON.stringify(response));
    var result = {"place_list":response};
    createResponse(response,"",200)
    res.json(apiResponse);
  });
});


//https://maps.googleapis.com/maps/api/place/nearbysearch/json?pagetoken=CrQCJQEAAJdeHsMGfQjj7ZUxGw2tXOOpbEKZH5tTpF8LBTChUiusZJ4fKX7lwoBDbKp96Q3sefih-oj3Lxvy_WazQLc2I2ypmMA4p6zZZOL4KyYaCcsg_cEuEFuabBKhuYlRHubvYjLO2-WneiWH9cFTJ5sUw9UIOpNmnvOwo2paF-5A8k8vxU0i_D7P0AaZDe9WxffZFOKbojgnxPnRAv4Au3hsSIpmOjA5Zy5aq6L1CtBBa811IX8492bl6f2UJw97-BGmKXz5v7Ori85xJkTcPDhXzgDliNMLAicMKfd3glbyAlwOMWnOdkJxmDQF8xncvA5D93aXZw5GLz2ZIdWt_X-D8bYOSw4cFEQNb8U1PjSEvJWyOqNf0anTbOK-vLhFIegEY8Qea74Bw0wfQjSrEbxJImESEHomiEsTEuYXz05y7rTcNtIaFDuz7FSlF4HZyRwdWOnOGF5EIjUT&key=AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us

// GeoCoding API

app.post('/getLocation',function(req,res){
  var host = "https://maps.googleapis.com/maps/api/geocode/json?";
  req.body.key="AIzaSyDcddz4rqeqdCVb2Yl7_bqKqDm8lslU1OA";

  console.log(JSON.stringify(req.body));

  var geocodeURL = host+querystring.stringify(req.body);

  console.log(geocodeURL);

  request(geocodeURL, function (error,response,body) {

    if (!error && response.statusCode == 200) {
      body = JSON.parse(body);
      var geocode = body.results[0];

      var result = {
                      address:geocode.formatted_address,
                      latitude:geocode.geometry.location.lat,
                      longitude:geocode.geometry.location.lng
                    };

      createResponse(result,"",response.statusCode);
      console.log(JSON.stringify(apiResponse));
    }
    else{
      createResponse("",error,response.statusCode);
    }
    res.json(apiResponse);
  });
});

// Place Id get Place Details

app.post('/place_details',function(req,res){
  var host = "https://maps.googleapis.com/maps/api/place/details/json?";
  req.body.key = "AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us";

  var place_list = [];

  console.log(JSON.stringify(req.body));

  var placeDetailURL = host+querystring.stringify(req.body);

  request(placeDetailURL, function (error,response,body) {
    if (!error && response.statusCode == 200) {



    }else{

    }

  });

});

// Place's API to get the details

app.post('/places',function(req,res){

  var host = "https://maps.googleapis.com/maps/api/place/nearbysearch/json?";
  req.body.key = "AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us";

  var next_page_token = "";
  var place_list = [];

  console.log(JSON.stringify(req.body));

  var placeURL = host+querystring.stringify(req.body);

  console.log(placeURL);
    //url = 'https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=34.0266,-118.2831&radius=500&type=restaurant&key=AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us';

    request(placeURL, function (error,response,body) {

      console.log(placeURL);

      if (!error && response.statusCode == 200) {
          console.log(JSON.stringify(response));
          body = JSON.parse(body);
          var places = body.results;

          console.log("places " + places.length);

          if(body.next_page_token!==undefined){
            next_page_token = body.next_page_token;
          }

          for(var key in places){
            var place = places[key];
            place_list.push(
              {
                Category : place.icon,
                Name : place.name,
                Address : place.vicinity,
                place_id : place.place_id,
                latitude : place.geometry.location.lat,
                longitude : place.geometry.location.log
              }
            );
          }

          var result = {"place_list":place_list,"next_page_token":next_page_token};
          createResponse(result,"",response.statusCode);
        }
        else{
          createResponse("",error,response.statusCode);
        }
        res.json(apiResponse);
      });
});

//app.listen(8081);
app.listen(3000, () => console.log('App is listening on port 3000!'))


//https://maps.googleapis.com/maps/api/place/nearbysearch/json?location=34.0266,-118.2831&radius=500&type=restaurant&key=AIzaSyBsHy8OvOaWhOwvPo98b3jOrxFCN4kr5Us
