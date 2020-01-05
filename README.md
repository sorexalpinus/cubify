# Cubify
Cubify your SQL query

Cubify package creates a patch over standard MySQL/MariaDB behaviour where CUBE function is not available.
All you need to do is choose your DBMS and provide your query, along with dimensions, measures and masks.

## Example

The table "spotted_animals" tracks animals spotted in different areas in Banff NP Canada in winter:

Year | Transect | Species | Snow depth | Num animals
--- | --- | --- | ---: | ---:
2015 | Dog Loop | NULL | 30 | 0
2014 | Cascade | fox | 2 | 1
2016 | Hoodoos | elk | 26 | 2
2015 | Airfield | elk | 11 | 7
2015 | Dog Loop | deer | 11 | 1
2016 | Airfield | coyote | 14 | 3 
2015 | Airfield | deer | 16 | 2 
2016 | Healy North | deer | 24 | 1                                                     
2016 | 40 Mile | coyote | 12 | 1
*more rows...* | | | | 

##### To find:
average snow depth and total number of animals spotted:
- per year,transect and species
- per year and transect, regardless of species
- per year and species, regardless of transect

##### use MysqlCube:

```php
$cube = new MysqlCube(
    //sql connection
    new mysqli('localhost','root','','wildlife_test',3308),
    //base query
    'SELECT * FROM spotted_animals',
    //masks
    ['111', '110', '101'],
    //dimensions
    ['Transect', 'Year', 'Species'],
    //measures & aggregate functions
     ['Snow depth' => 'AVG', 'Num animals' => 'SUM']
);
$data = $cube->getResultDataset();
print_r($data);
```

5 arguments were passed:
1. MySQL database connection
2. Base query - this is a statement you want to cubify (use CUBE)
3. Masks represent grouping sets with regard to dimensions` order (see table below)
4. Dimensions are "GROUP BY" columns
5. Measures are numbers to be aggregated by per each combination

Mask | Transect | Year | Species | Snow depth | Num animals
--- | --- | ---  | --- | --- | ---
111 | value | value | value | AVG(Snow depth) | SUM(Num animals)
110 | value | value | (total) | AVG(Snow depth) | SUM(Num animals)
101 | value | (total) | value | AVG(Snow depth) | SUM(Num animals)

##### Use output:

Method getResultDataset() returns array which can be translated to a following table:

Mask | Transect | Year | Species | Snow depth | Num animals
--- | --- | --- | --- | ---: | ---:    
101 | Cascade | (total) | fox | 18.0000 | 10
101 | Dog Loop | (total) | elk | 25.7895 | 44
101 | Dog Loop | (total) | fox | 21.5000 | 2
110 | Cascade | 2014 | (total) | 15.5444 | 122
110 | Cascade | 2015 | (total) | 18.4649 | 114
110 | Cascade | 2016 | (total) | 19.3472 | 116
110 | Dog Loop | 2014 | (total) | 10.1221 | 127
110 | Dog Loop | 2015 | (total) | 25.7264 | 102
110 | Dog Loop | 2016  | (total) | 21.3565 | 172
111 | Cascade | 2014 | elk | 21.4118 | 90
111 | Cascade | 2014 | fox | 2.0000 | 2
111 | Cascade | 2015 | elk | 19.7500 | 57
111 | Cascade | 2015 | fox | 22.5714 | 8
111 | Cascade | 2016 | elk | 21.6875 | 46
111 | Dog Loop | 2014 | elk | 6.5000 | 6
111 | Dog Loop | 2015 | elk | 31.0000 | 19
111 | Dog Loop | 2016 | fox | 21.5000 | 2
*more rows...* | | | | | 
## Output methods

**`getCubeQuery()`** returns final query that can be used for further SQL operations

**`getResult()`** returns SQL result object

**`getResultDataset()`** returns the complete dataset as an array

## Known limitations    
- Cubify does not provide Cartesian cube (all combinations for all dimensions values), the final dataset only contains only combinations that exist in the base query dataset
- At the moment, it can be only used with MySQL (though other "cubes" can be added easily implementing the SqlCube interface)