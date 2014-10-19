geojson2svg
===========

This is a simple PHP library to convert a geojson file to svg file.

Usage
-----
```
geojson2svg($filename, $options)
```

Parameters
----------
<b>$filename</b> can contain path to the input file.<br/>
The output file will be in the same directory as the input file.<br/>
To make this function only works with geojson string as an input and outputs an svg string, just remove the file operation statements at the beginning and end of the main function, then change the <i>$filename</i> parameter to <i>$json</i>, and make it returns the <i>$buffer</i> string.

<b>$options</b> is ommitable.<br/>
But shall you need to tweak the appearance of the SVG, it is an array of:<br/>
<b>ratio</b> - defines the magnification ratio<br/>
<b>canvasWidth</b> - defines canvasWidth in unit measurement x ratio<br/>
<b>canvasHeight</b> - defines canvasHeight<br/>
<b>top</b> - defines top offset from the canvas (0,0) point<br/>
<b>left</b> - defines left offset from the canvas (0,0) point<br/>
<b>minXYPixel</b> - minimum width or height of the polygon in pixel for the label to be drawn. If a polygon has width or height less than this value, label won't be drawn over it.<br/>
<b>fontSize</b> - defines the font size for the label.<br/>
<b>lineSpacing</b> - defines the line spacing of the label if it spans multiple lines.

If you are familiar with choropleth map and want to make the output svg colorful, the <i>'render_feature'</i> function is where you might want to look and customize. Or you can modify this library to work with an external callback-function as the color factory.

Return Values
-------------
This function does not return any value if successful, but it will return an <i>&lt;error&gt;</i> block if it encounters an error, or need to end the process prematurely.

Errors/Exceptions
-----------------
If the geojson file contains 'weird' geojson format - that is the top level object does not have <i>'type'</i> property and is not an array of object with <i>'type'</i> property of <i>'Features'</i> - this library will yield an <i>'Unsupported GeoJSON format'</i> error.<br/>
Also, if the geojson does not contains any <i>'Polygon'</i> or <i>'Multipolygon'</i> feature, the library will quit immediately and sign you that it doesn't found any polygonic feature as an <i>'No polygon found'</i> error.
