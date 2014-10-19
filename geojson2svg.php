<?php
function geojson2svg ( $filename, $options=null ) {
	// Default pixel per mm ratio
	$defRatio = 2.5;
	// Default canvas dimension of A4 paper size (210x297mm), with 20,40,10,25 mm 
	// top, left, bottom, and right margins respectively
	$defCanvasWidth = 180 * $defRatio;
	$defCanvasHeight = 232 * $defRatio;
	// Default left and top offset from the margin
	$defTop = 0 * $defRatio;
	$defLeft = 0 * $defRatio;
	// Trivial: minimal width or height of a polygon to be labeled
	$defMinXYPixel = 15;
	$defFontSize = 6;
	$defLineSpacing = 8;

	if ( ! isset( $options ) ) {
		$options = array();
	}

	if ( isset( $options[ 'ratio' ] ) ) {
		$ratio = $options[ 'ratio' ];
	} else {
		$ratio = $defRatio;
	}
	if ( isset( $options[ 'canvasWidth' ] ) ) {
		$canvasWidth = $options[ 'canvasWidth' ];
	} else {
		$canvasWidth = $defCanvasWidth;
	}
	if ( isset( $options[ 'canvasHeight' ] ) ) {
		$canvasHeight = $options[ 'canvasHeight' ];
	} else {
		$canvasHeight = $defCanvasHeight;
	}
	if ( isset( $options[ 'top' ] ) ) {
		$top = $options[ 'top' ];
	} else {
		$top = $defTop;
	}
	if ( isset( $options[ 'left' ] ) ) {
		$left = $options[ 'left' ];
	} else {
		$left = $defLeft;
	}

	if ( ! isset( $options[ 'minXYPixel'] ) ) {
		$options[ 'minXYPixel' ] = $defMinXYPixel;
	}
	if ( ! isset( $options[ 'fontSize'] ) ) {
		$options[ 'fontSize' ] = $defFontSize;
	}
	if ( ! isset( $options[ 'lineSpacing'] ) ) {
		$options[ 'lineSpacing' ] = $defLineSpacing;
	}

	$geojson = file_get_contents( $filename );
	if ( $geojson === false ) {
		return '<error>Fail to read input file</error>';
	}
	$object = json_decode( $geojson, true );
	if ( isset( $object[ 'type' ] ) ) {
		if ( $object[ 'type' ] === 'FeatureCollection' ) {
			$features = $object[ 'features' ];
		} else { // assume the object is a single polygon or another primitive
			$features = array( $object );
		}
	} else {
		if ( gettype( $object ) === 'array' && 
			 isset( $object[ 0 ][ 'type' ] ) && 
			 $object[ 0 ][ 'type' ] === 'Feature' ) {
			$features = $object;
		} else {
			return '<error>Unsupported GeoJSON format</error>';
		}
	}

	$bounds = array(
		'minX' => null, 
		'minY' => null,
		'maxX' => null,
		'maxY' => null
	);
	foreach ( $features as $feature ) {
		$geometry = $feature[ 'geometry' ];
		$coordinates = $geometry[ 'coordinates' ];
		switch ( $geometry[ 'type' ] ) {
			case 'Polygon':
			register_polygon( $coordinates, $bounds );
			break;
			case 'MultiPolygon':
			foreach ( $coordinates as $polygon ) {
				register_polygon ( $polygon, $bounds );
			}
		}
	}
	// Check if the geojson doesn't contain any single polygon or multipolygon
	if ( ! isset( $bounds[ 'maxX' ] ) ) {
		// then we should stop and return a message as an error
		return '<error>No polygon found</error>';
	}
	// else: proceed
	$width = $bounds[ 'maxX' ] - $bounds[ 'minX' ];
	$height = $bounds[ 'maxY' ] - $bounds[ 'minY' ];
	$scaleX = $canvasWidth / $width;
	$scaleY = $canvasHeight / $height;
	$scale = min( $scaleX, $scaleY );
	$options[ 'scale' ] = $scale;
	$options[ 'ox' ] = $left - ( $scale * $bounds[ 'minX' ] );
	// In svg, positive-y is downward
	$options[ 'oy' ] = $top - ( -$scale * $bounds[ 'maxY' ] );
	
	$buffer = '<svg width="' . round( $scale * $width ) . '" height="' . 
		round( $scale * $height ) . '">';
	$textBuffer = '';
	foreach ( $features as $feature ) {
		list($buf, $textBuf) = render_feature( $feature, $options );
		$buffer .= $buf; $textBuffer .= $textBuf;
	}
	$buffer .= $textBuffer;
	$buffer .= '</svg>';

	$svgfile = preg_replace( '/\.g?(eo)?json$/', '', $filename ) . ".svg";
	$bytes = file_put_contents( $svgfile, $buffer );
	if ( $bytes > 0 )
		echo "$filename --> $svgfile.<br/>" . PHP_EOL;
}

function register_polygon ( $coordinates, &$bounds ) {
	foreach ( $coordinates as $subcoordinates ) {
		foreach ( $subcoordinates as $coordinate ) {
			if ( ! isset( $bounds[ 'minX' ] ) )
				$bounds[ 'minX' ] = $coordinate[ 0 ];
			else if ( $coordinate[ 0 ] < $bounds[ 'minX' ] )
				$bounds[ 'minX' ] = $coordinate[ 0 ];
			if ( ! isset( $bounds[ 'maxX' ] ) )
				$bounds[ 'maxX' ] = $coordinate[ 0 ];
			else if ( $coordinate[ 0 ] > $bounds[ 'maxX' ] )
				$bounds[ 'maxX' ] = $coordinate[ 0 ];

			if ( ! isset( $bounds[ 'minY' ] ) )
				$bounds[ 'minY' ] = $coordinate[ 1 ];
			else if ( $coordinate[ 1 ] < $bounds[ 'minY' ] )
				$bounds[ 'minY' ] = $coordinate[ 1 ];
			if ( ! isset( $bounds[ 'maxY' ] ) )
				$bounds[ 'maxY' ] = $coordinate[ 1 ];
			else if ( $coordinate[ 1 ] > $bounds[ 'maxY' ] )
				$bounds[ 'maxY' ] = $coordinate[ 1 ];
		}
	}
	return $bounds;
}

function render_feature ( $feature, $options ) {
	// This is a bucket of 5-shades of gray
	$colors = array( 'rgb(247,247,247)','rgb(204,204,204)','rgb(150,150,150)',
		'rgb(99,99,99)','rgb(37,37,37)' );

	$geometry = $feature[ 'geometry' ];
	$coordinates = $geometry[ 'coordinates' ];
	$text = isset( $feature[ 'properties' ][ 'name' ] ) 
	        ? $feature[ 'properties' ][ 'name' ] 
	        : '';
	$color = 'gray';

	switch ( $geometry[ 'type' ] ) {
		case 'Polygon':
		return render_polygon( $coordinates, $color, $text, $options );
		break;
		case 'MultiPolygon':
		$buffer = ''; $textBuffer = '';
		foreach ( $coordinates as $polygon ) {
			list($buf, $textBuf) = render_polygon ( $polygon, $color, $text, $options );
			$buffer .= $buf; $textBuffer .= $textBuf;
		}
		return array($buffer, $textBuffer);
	}
}

function render_polygon ( $coordinates, $fill, $text, $options ) {
	$scale = $options[ 'scale' ];
	$ox = $options[ 'ox' ];
	$oy = $options[ 'oy' ];
	$fontSize = $options[ 'fontSize' ];
	$lineSpacing = $options[ 'lineSpacing' ];

	$buffer = ''; $textBuffer = '';
	foreach ( $coordinates as $subcoordinates ) {
		$_minX; $_minY; $_maxX; $_maxY; // to compute the center point
		$buffer .= '<polygon points="';
		$first = true;
		$sumX = 0; $sumY = 0; $count = 0;
		foreach ( $subcoordinates as $coordinate ) {
			if ( $first ) $first = false;
			else $buffer .= " ";

			$x = round( $scale * $coordinate[ 0 ] + $ox );
			$y = round( -$scale * $coordinate[ 1 ] + $oy );
			$buffer .= $x . ',' . $y;

			if ( ! isset( $_minX ) )
				$_minX = $x;
			else if ( $x < $_minX )
				$_minX = $x;
			if ( ! isset( $_maxX ) )
				$_maxX = $x;
			else if ( $x > $_maxX )
				$_maxX = $x;

			if ( ! isset( $_minY ) )
				$_minY = $y;
			else if ( $y < $_minY )
				$_minY = $y;
			if ( ! isset( $_maxY ) )
				$_maxY = $y;
			else if ( $y > $_maxY )
				$_maxY = $y;

			$sumX += $x;
			$sumY += $y;
			$count++;
		}
		$buffer .= "\" style=\"fill:$fill;stroke:white;stroke-width:2\"/>";

		// If dx or dy is less than $minXYPixel pixels, don't create label
		$dx = $_maxX - $_minX; $dy = $_maxY - $_minY;
		$dp = $options[ 'minXYPixel' ];
		if ( $dx < $dp || $dy < $dp )
			continue;
		// Compute the polygon's center point as the average of x & y
		$textX = round( $sumX / $count );
		$textY = round( $sumY / $count );

		if ( $fill === 'rgb(99,99,99)' || $fill === 'rgb(37,37,37)') {
			$textFill = 'white';
		} else {
			$textFill = 'black';
		}
		$textBuffer .= "<text x=\"$textX\" y=\"$textY\" text-anchor=\"middle\" " . 
		               "fill=\"$textFill\" style=\"font-size:$fontSize\">";

		// If the text is longer than one word, split it one line per word
		$textArray = explode( " ", $text );
		if ( count( $textArray ) > 1 ) {
			$first = true;
			foreach ($textArray as $textSpan) {
				if ( $first ) {
					$textBuffer .= "<tspan>$textSpan</tspan>";
					$first = false;
				} else {
					$textBuffer .= "<tspan x=\"$textX\" dy=\"$lineSpacing\">" . 
					               "$textSpan</tspan>";
				}
			}
		} else {
			$textBuffer .= $text;
		}

		$textBuffer .= '</text>';
	}
	return array($buffer, $textBuffer);
}
?>
