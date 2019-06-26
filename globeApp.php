<!DOCTYPE html>
<html>
<head>
  <title>globeApp</title>
  <meta charset="utf-8">
  <link rel = "stylesheet" type = "text/css" href = "global.css" />
</head>


<body>
  <div id="border-controller">
    <div id="map">
      <div id="countryDataBox">  </div>
    <!--A div for top or bottom of data ranges-->
      <div id="dataStandardDeviations"></div>
    </div>
      <div id="controller">
        <button id="controller-mapType">Toggle 3D</button>
        <button id="selector-gdp">GDP</button>
        <button id="selector-literacy">Literacy</button>
        <button id="selector-population">Population</button>
      </div>
  </div>
  

 



  <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/4.2.2/d3.min.js"></script>
  <script src="http://d3js.org/topojson.v1.min.js"></script>
  <!--math functions for rotating 3d globe object-->
  <script src="js/mathFunctions.js"></script>
  <!--<script src="visualizaton.js"></script>-->



<script type="text/javascript">

//main data variable, 
  //countryStatistics = mySQL query of country data
  // dataById = the topojson arc data with the country statistics added

  var countryStatistics = [];
  var dataById = [];

  var dataType =            "csv";  //change to "sql" to load data from MySQL database/"csv" load from csv file
  var statisticsToDisplay = "gdp";  //initial data to display onPageLoad,  "population", "gdp" etc


  function visualizeInit(){
      //retrieve data from MySQL or local CSV file
    if(dataType == 'sql'){
            getCountryDataSQL();
    }else if (dataType === 'csv'){
          getCountryDataCSV();
    }

  }visualizeInit();


  //this function to be called only when data sets are drawn from a MySQL database
      function getCountryDataSQL(){
        var dataXMLhttp = new XMLHttpRequest();
      //open the request and store parameters for the request.  GET (or POST) is method to send,
      //the pathway or url of the data is specified, and whether or not the request is asynchronous
        dataXMLhttp.open("GET", "./sqlFunctions/returnData.php", true);
      //send the request
        dataXMLhttp.send();
        dataXMLhttp.onreadystatechange=function(){
      //there conditions allow the request to wait until the server respondes that it is ready.
          if(dataXMLhttp.readyState == 4 && dataXMLhttp.status == 200){
        //the response is stored in a variable
            XMLdataResult = dataXMLhttp.responseText;
            //window ties the variable to the global window object
            countryStatistics = eval(XMLdataResult);

            //place country statistics into array of objects key = topoId
            keyIdToData();
            visualize(statisticsToDisplay, 'orthographic');
          }
        }
      }

     //In this case country statistics are stored in a local CSV file
      function getCountryDataCSV() {
        console.log("loading data from CSV file");

        d3.csv("countryStats1.csv", function(data){
        for(var i = 0; i < data.length; i++) {
          countryStatistics.push(data[i]);
        }
        keyIdToData();
        visualize(statisticsToDisplay, 'orthographic');
      
        })   
      }


  function visualize(dataSelected, mapType){
    //pass arguments each function call to decide what data to viasually display, and what map type to use

    //set up data and domains and ranges : range used for controlling color gradients
    var literacyDomain  = [30, 100];
    var gdpDomain       = [0, 55100];
    var populationDomain = [2000000, 1320000000];


      //switch to set color scale domain based on what data is being called
    switch (dataSelected) {
      case "gdp":
      console.log("GDP Recognized");
        var colorScale = d3.scaleLinear()
          .domain(gdpDomain)
          .range(["#646464", "#ffff00"])
        break;
      case "literacy":
        console.log("Literacy Recognized");
        var colorScale = d3.scaleLinear()
          .domain(literacyDomain)
          .range(["#646464", "#ffff00"])
        break;
      case "population":
        console.log("Population Recognized");
        var colorScale = d3.scaleLog()
          .domain(populationDomain)
          .range(["#646464", "#ffff00"])
        break;
    }

//set margins and positioning
    var margin = {top: 50, left: 0, right: 0, bottom:50},
        height = window.innerHeight - margin.top - margin.bottom, 
        width  = window.innerWidth - margin.left - margin.right - 100;

//create svg
    var svg = d3.select("#map")
          .append("svg")
          .attr("height", height + margin.top + margin.bottom)
          .attr("width", width + margin.left + margin.right)
          .append("g") //do I need this line of code?
          .attr("transform", "translate(" + margin.left + "," + margin.top + ")")
    


      //set projection type to 2D map or 3d globe
    var projection = setMapType(mapType, width, height);


      //pass path lines to projections
    var path = d3.geoPath()
      .projection(projection);

    if(mapType == "orthographic"){
      var drag = d3.drag()
        .on("start", dragstarted)
        .on("drag", dragged);
        svg.call(drag);

        //globe rotation coordenant variables
      var gpos0, 
          o0;

      function dragstarted(){
        gpos0 = projection.invert(d3.mouse(this));
        o0 = projection.rotate();  
      }

      function dragged(){
        var gpos1 = projection.invert(d3.mouse(this));
        o0 = projection.rotate();

        var o1 = eulerAngles(gpos0, gpos1, o0);
        projection.rotate(o1);

        svg.selectAll("path").attr("d", path);
      }
    }



    d3.queue()
      .defer(d3.json, "world110m.json")
      .await(ready) 



  function ready (error, data){
      if (error) throw error;

      //we set the the whole globe as a sphere to interact with drag events
    if(mapType == 'orthographic'){
      var globeSphere = {type:"Sphere"}
          svg.append("path")
          .datum(globeSphere)
          .attr("d", path)
          .attr("fill","lightblue");      
    }

 
    countries = topojson.feature(data, data.objects.countries)
    //bind dataById data into countries topojson variable
    .features.map(function(d) {
      d.properties = dataById[d.id];
      return d
    });



    svg.selectAll(".country")
      .data(countries)
      .enter().append("path")
      .attr("class", "country")
      .attr("d", path)

      //make fill gradient depend on data
      //use a switch and pass to a function tailored to display correct data
      .attr("fill", function(countries){
        if(countries.properties == undefined){
          return "rgb(100 100 100)";
        }else{
          //access different data properties depending on parameter dataSelected
        return colorScale(countries.properties[dataSelected])
        }
      })
      .on('mouseover', function(d) {
        d3.select(this).classed("hovered", true)

          //function to call the corrsponding countryStatisit object to print idNumber
        let country = matchPath(this.__data__.id);
      appendCountryDataBox(country);

      })
      .on('mouseout', function(d) {
        d3.select(this).classed("hovered", false)

      })
    }
  };

  //function to plug in specified data
function dataSelector(dataSelected) {

}

  //this function build dataById[] setting data keyed to idTopo
function keyIdToData(d){
  countryStatistics.forEach(function(d) {
    dataById[d.idTopo] = d;
  });  
}    

    //this function matches countryStatistcs data to each individual country when hovered
  function matchPath(pathId){
    for(var i = 0; i < countryStatistics.length; i++){
      if(pathId == countryStatistics[i].idTopo){
        return countryStatistics[i];
      }
    }
  }

//for ZOOM we need to pass a parameter here to scale

    //a function to call on visualize() to set projection type for map style.
  function setMapType(mapType, width, height) {
    if(mapType === "mercator") {
      let projection = d3.geoMercator()
      .translate([ width / 2, height / 2 ])
      .scale(180)
      return projection;
    }else if (mapType === "orthographic"){
      let projection = d3.geoOrthographic()
      .clipAngle(90)
      .scale(240);
      return projection;
    }
  }

  function appendCountryDataBox(countryData) {
    var countryDataBox =  "<h4>Country: " + countryData.country + "</h4><p>Population: " + countryData.population + "</p><p>GDP: " + countryData.gdp + "</p><p>Literacy Rate: " + countryData.literacy + "</p>" ;
    document.getElementById('countryDataBox').innerHTML = countryDataBox;
  }

  //Directions to user
window.alert("Click and Drag to Rotate Globe");



  </script>

  <script type="text/javascript">
    //addEvent Listeners
  var mapIsGlobe = true;
    document.getElementById("controller-mapType").addEventListener("click", function() {
      d3.select("svg").remove();
      if(mapIsGlobe){
        mapIsGlobe = false;
        visualize(statisticsToDisplay, 'mercator');
      }else if (!mapIsGlobe){
        mapIsGlobe = true;
        visualize(statisticsToDisplay, 'orthographic');
      }
    });

    document.getElementById("selector-gdp").addEventListener("click", function() {
      d3.select("svg").remove();
      statisticsToDisplay = "gdp";
      if(mapIsGlobe){
        visualize(statisticsToDisplay, 'orthographic');
      }else if (!mapIsGlobe){
        visualize(statisticsToDisplay, 'mercator');
      }
    });
    document.getElementById("selector-literacy").addEventListener("click", function() {
      d3.select("svg").remove();
      statisticsToDisplay = "literacy";
      if(mapIsGlobe){
        visualize(statisticsToDisplay, 'orthographic');
      }else if (!mapIsGlobe){
        visualize(statisticsToDisplay, 'mercator');
      }
    });

        document.getElementById("selector-population").addEventListener("click", function() {
      d3.select("svg").remove();
      statisticsToDisplay = "population";
      if(mapIsGlobe){
        visualize(statisticsToDisplay, 'orthographic');
      }else if (!mapIsGlobe){
        visualize(statisticsToDisplay, 'mercator');
      }
    });
 
  </script>
</body>
</html>
