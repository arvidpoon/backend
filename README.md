# Backend

## Docker
- Laradock

## Framework
- Codeigniter 3

## PHP version
- fpm 7.1 

## Start the docker
```
cd laradock
docker-compose up -d nginx
```

## Description

### Submit locations
- Submit start point and dropoff point (coordinate), also some more waypoints (coordinate)
- REST API server received the 'POST' request
- Generate a token
- Create a textfile named as generated token, marked status as 'progress'
- Validate the submited points format. Return error if the format is not correct
- At least 2 coordinate points (start point and dropoff point). Return error if necessary
- Validate the coordinate value (-90.0000000 <= latitude <= 90.0000000, -180.0000000 <= longitude <= 180.0000000). Return error if invalid
- Check 3 times from Google API - regular, avoid highways and avoid tolls
- If no route found, return error
- Calculate all routes and get the shortest path. Write information back to the textfile created before, marked status as 'completed'
- Return the token to user

### Get shortest driving route
- Submit the token returned from system
- By using the token as filename, check the file if existed or not. If not existed, return error 'Invalid token'
- If file exised, read the content and check the status. If the status is 'progress', return 'Progress'
- Get all steps from route, format the output
- Return the output to user

## Usage
Method:
- `POST`

URL path:
- `/route`

Field name:
`path`

Value:
```json
[
	["ROUTE_START_LATITUDE", "ROUTE_START_LONGITUDE"],
	["DROPOFF_LATITUDE_#1", "DROPOFF_LONGITUDE_#1"],
	...
]
```

Response body:  
 - `HTTP code 200`  

```json
{ "token": "TOKEN" }
```

or

```json
{ "error": "ERROR_DESCRIPTION" }
```

Method:
- `GET`

URL path:
- `/route/<TOKEN>`

Response:
```json
[
	["ROUTE_START_LATITUDE", "ROUTE_START_LONGITUDE"],
	["DROPOFF_LATITUDE_#1", "DROPOFF_LONGITUDE_#1"],
	...
]
```

Response body:  
- HTTP 200  

```json
{
	"status": "success",
	"path": [
		["ROUTE_START_LATITUDE", "ROUTE_START_LONGITUDE"],
		["DROPOFF_LATITUDE_#1", "DROPOFF_LONGITUDE_#1"],
		...
	],
	"total_distance": DRIVING_DISTANCE_IN_METERS,
	"total_time": ESTIMATED_DRIVING_TIME_IN_SECONDS
}
```  
or  

```json
{
	"status": "in progress"
}
```  
or  

```json
{
	"status": "failure",
	"error": "ERROR_DESCRIPTION"
}
```

## Demo
```
- Submit
1. 	http://localhost/
	5 locations will be sent
	[
		["22.372081", "114.107877"],
		["22.284419", "114.159510"],
		["22.3469144", "114.1981317"],
		["22.326442", "114.167811"],
		["22.3508364", "114.2454191",
	]
```

```
- Get
http://localhost/route/\<token>
```