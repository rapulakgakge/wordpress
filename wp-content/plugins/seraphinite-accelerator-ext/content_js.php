<?php

namespace seraph_accel;

if( !defined( 'ABSPATH' ) )
	exit;

function _Scripts_EncodeBodyAsSrc( $cont )
{

	$cont = str_replace( "%", '%25', $cont );

	$cont = str_replace( "\n", '%0A', $cont );
	$cont = str_replace( "#", '%23', $cont );
	$cont = str_replace( "\"", '%22', $cont );

	return( $cont );
}

function IsScriptTypeJs( $type )
{
	return( !$type || $type == 'application/javascript' || $type == 'text/javascript' || $type == 'module' );
}

function Script_SrcAddPreloading( $item, $src, $head, $doc )
{
	if( !$src )
		return;

	$itemPr = $doc -> createElement( 'link' );
	$itemPr -> setAttribute( 'rel', 'preload' );
	$itemPr -> setAttribute( 'as', $item -> tagName == 'IFRAME' ? 'document' : 'script' );
	$itemPr -> setAttribute( 'href', $src );
	if( $item -> hasAttribute( 'integrity' ) )
		$itemPr -> setAttribute( "integrity", $item -> getAttribute( "integrity" ) );
	if( $item -> hasAttribute( "crossorigin" ) )
		$itemPr -> setAttribute( "crossorigin", $item -> getAttribute( "crossorigin" ) );
	$head -> appendChild( $itemPr );
}

function Scripts_Process( &$ctxProcess, $sett, $settCache, $settContPr, $settJs, $settCdn, $doc )
{
	if( (isset($ctxProcess[ 'isAMP' ])?$ctxProcess[ 'isAMP' ]:null) )
	    return( true );

	$optLoad = Gen::GetArrField( $settJs, array( 'optLoad' ), false );
	$skips = Gen::GetArrField( $settJs, array( 'skips' ), array() );

	if( !( $optLoad || Gen::GetArrField( $settJs, array( 'groupNonCrit' ), false ) || Gen::GetArrField( $settJs, array( 'min' ), false ) || Gen::GetArrField( $settCdn, array( 'enable' ), false ) || $skips ) )
		return( true );

	if( (isset($ctxProcess[ 'compatView' ])?$ctxProcess[ 'compatView' ]:null) )
		$optLoad = false;

	$aGrpExcl = Gen::GetArrField( $settJs, array( 'groupExcls' ), array() );
	$notCritsDelayTimeout = Gen::GetArrField( $settJs, array( 'nonCrit', 'timeout', 'enable' ), false ) ? Gen::GetArrField( $settJs, array( 'nonCrit', 'timeout', 'v' ), 0 ) : null;

	$specsDelayTimeout = Gen::GetArrField( $settJs, array( 'spec', 'timeout', 'enable' ), false ) ? Gen::GetArrField( $settJs, array( 'spec', 'timeout', 'v' ), 0 ) : null;
	$specs = ( ( $notCritsDelayTimeout !== null && $specsDelayTimeout ) || ( $notCritsDelayTimeout === null && $specsDelayTimeout !== null ) ) ? Gen::GetArrField( $settJs, array( 'spec', 'items' ), array() ) : array();

	$head = $ctxProcess[ 'ndHead' ];
	$body = $ctxProcess[ 'ndBody' ];

	$settNonCrit = Gen::GetArrField( $settJs, array( 'nonCrit' ), array() );

	$delayNotCritNeeded = false;
	$delaySpecNeeded = false;

	$items = HtmlNd::ChildrenAsArr( $doc -> getElementsByTagName( 'script' ) );

	$contGroups = array( 'crit' => array( array( 0, 0 ), array( '' ) ), '' => array( array( 0, 0 ), array( '' ) ), 'spec' => array( array( 0, 0 ), array( '' ) ) );

	foreach( $items as $item )
	{
		if( ContentProcess_IsAborted( $settCache ) ) return( true );

		$type = HtmlNd::GetAttrVal( $item, 'type' );
		if( !IsScriptTypeJs( $type ) )
			continue;

		if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
		{
			if( !$type )
				$item -> setAttribute( 'type', $type = 'text/javascript' );
		}
		else if( $type && (isset($settContPr[ 'min' ])?$settContPr[ 'min' ]:null) && $type != 'module' )
		{
			$item -> removeAttribute( 'type' );
			$type = null;
		}

		$src = HtmlNd::GetAttrVal( $item, 'src' );
		$id = HtmlNd::GetAttrVal( $item, 'id' );
		$cont = $item -> nodeValue;

		{

		}

		$detectedPattern = null;
		if( IsObjInRegexpList( $skips, array( 'src' => $src, 'id' => $id, 'body' => $cont ), $detectedPattern ) )
		{
			if( (isset($ctxProcess[ 'debug' ])?$ctxProcess[ 'debug' ]:null) )
			{
				$item -> setAttribute( 'type', 'o/js-inactive' );
				$item -> setAttribute( 'seraph-accel-debug', 'status=skipped;' . ( $detectedPattern ? ' detectedPattern="' . $detectedPattern . '"' : '' ) );
			}
			else
				$item -> parentNode -> removeChild( $item );
			continue;
		}

		$detectedPattern = null;
		if( $src )
		{
			$srcInfo = GetSrcAttrInfo( $ctxProcess, null, null, $src );

			if( (isset($srcInfo[ 'filePath' ])?$srcInfo[ 'filePath' ]:null) && Gen::GetFileExt( $srcInfo[ 'filePath' ] ) == 'js' )
				$cont = @file_get_contents( $srcInfo[ 'filePath' ] );
			if( !$cont )
			{
				$cont = GetExtContents( (isset($srcInfo[ 'url' ])?$srcInfo[ 'url' ]:null), $contMimeType );
				if( $cont !== false && !in_array( $contMimeType, array( 'text/javascript', 'application/x-javascript', 'application/javascript' ) ) )
				{
					$cont = false;
					if( (isset($sett[ 'debug' ])?$sett[ 'debug' ]:null) )
						LastWarnDscs_Add( LocId::Pack( 'JsUrlWrongType_%1$s%2$s', null, array( $srcInfo[ 'url' ], $contMimeType ) ) );
				}
			}

			$isCrit = $item -> hasAttribute( 'seraph-accel-crit' ) ? true : GetObjSrcCritStatus( $settNonCrit, $specs, $srcInfo, $src, $id, $cont, $detectedPattern );

			if( Script_AdjustCont( $ctxProcess, $settCache, $settJs, $srcInfo, $src, $id, $cont ) )
			{
				if( (isset($ctxProcess[ 'debug' ])?$ctxProcess[ 'debug' ]:null) )
					$cont = '// ################################################################################################################################################' . "\r\n" . '// DEBUG: seraph-accel JS src="' . $src . '"' . "\r\n\r\n" . $cont;

				if( !adkxsshiujqtfk( $ctxProcess, $settCache, 'js', $cont, $src ) )
					return( false );
			}

			Cdn_AdjustUrl( $ctxProcess, $settCdn, $src, 'js' );
			Fullness_AdjustUrl( $ctxProcess, $src, (isset($srcInfo[ 'srcUrlFullness' ])?$srcInfo[ 'srcUrlFullness' ]:null) );

			$item -> setAttribute( 'src', $src );
		}
		else
		{
			if( !$cont )
				continue;

			$isCrit = $item -> hasAttribute( 'seraph-accel-crit' ) ? true : GetObjSrcCritStatus( $settNonCrit, $specs, null, null, $id, $cont, $detectedPattern );

			if( Script_AdjustCont( $ctxProcess, $settCache, $settJs, null, null, $id, $cont ) )
			{
				if( (isset($ctxProcess[ 'debug' ])?$ctxProcess[ 'debug' ]:null) )
					$cont = '// ################################################################################################################################################' . "\r\n" . '// DEBUG: seraph-accel JS src="inline:' . (isset($ctxProcess[ 'serverArgs' ][ 'REQUEST_SCHEME' ])?$ctxProcess[ 'serverArgs' ][ 'REQUEST_SCHEME' ]:null) . '://' . $ctxProcess[ 'host' ] . ':' . (isset($ctxProcess[ 'serverArgs' ][ 'SERVER_PORT' ])?$ctxProcess[ 'serverArgs' ][ 'SERVER_PORT' ]:null) . (isset($ctxProcess[ 'serverArgs' ][ 'REQUEST_URI' ])?$ctxProcess[ 'serverArgs' ][ 'REQUEST_URI' ]:null) . ':' . $item -> getLineNo() . '"' . "\r\n\r\n" . $cont;

				HtmlNd::SetValFromContent( $item, $cont );
			}
		}

		ContUpdateItemIntegrity( $item, $cont );

		if( (isset($ctxProcess[ 'debug' ])?$ctxProcess[ 'debug' ]:null) )
			$item -> setAttribute( 'seraph-accel-debug', 'status=' . ( $isCrit === true ? 'critical' : ( $isCrit === null ? 'special' : 'nonCritical' ) ) . ';' . ( $detectedPattern ? ' detectedPattern="' . $detectedPattern . '"' : '' ) );

		$delay = 0;
		if( $optLoad )
		{
			if( !$isCrit )
			{
				$parentNode = $item -> parentNode;
				$async = $item -> hasAttribute( 'async' );

				$delay = ( $isCrit === null ) ? $specsDelayTimeout : $notCritsDelayTimeout;

				if( $delay === 0 && ( !$async || ( $parentNode === $head || $parentNode === $body ) ) )
					$body -> appendChild( $item );
			}

		}

		if( (isset($ctxProcess[ 'chunksEnabled' ])?$ctxProcess[ 'chunksEnabled' ]:null) )
			ContentMarkSeparate( $item, false );

		if( $delay )
		{
			if( $type )
				$item -> setAttribute( 'data-type', $type );

			if( $isCrit === null )
			{

				$item -> setAttribute( 'type', 'o/js-lzls' );
				$delaySpecNeeded = true;
			}
			else
			{

				$item -> setAttribute( 'type', 'o/js-lzl' );
				$delayNotCritNeeded = true;
			}
		}

		if( !(isset($ctxProcess[ 'compatView' ])?$ctxProcess[ 'compatView' ]:null) && (isset($settJs[ $isCrit ? 'group' : ( $isCrit === null ? 'groupSpec' : 'groupNonCrit' ) ])?$settJs[ $isCrit ? 'group' : ( $isCrit === null ? 'groupSpec' : 'groupNonCrit' ) ]:null) )
		{
			if( (isset($ctxProcess[ 'debug' ])?$ctxProcess[ 'debug' ]:null) && is_string( $cont ) )
				$cont = '/* ################################################################################################################################################ */' . "\r\n" . '/* DEBUG: seraph-accel JS src="' . $src . '" */' . "\r\n\r\n" . $cont;

			$bGrpExcl = ( Gen::GetArrField( $settJs, array( 'groupExclMdls' ) ) && $type == 'module' ) || IsObjInRegexpList( $aGrpExcl, array( 'src' => $src, 'id' => $id, 'body' => $cont ) );

			if( $cont === false || $bGrpExcl )
				$cont = '';

			if( substr( $cont, -1, 1 ) == ';' )
				$cont .= "\r\n";
			else
				$cont .= ";\r\n";

			if( (isset($ctxProcess[ 'chunksEnabled' ])?$ctxProcess[ 'chunksEnabled' ]:null) && Gen::GetArrField( $settCache, array( 'chunks', 'js' ) ) )
				$cont .= ContentMarkGetSep();

			if( $optLoad && $isCrit === false && $delayNotCritNeeded )
				$cont .= 'seraph_accel_gzjydy();';

			$contGroup = &$contGroups[ $isCrit ? 'crit' : ( $isCrit === null ? 'spec' : '' ) ];

			if( ( $item -> hasAttribute( 'defer' ) && $item -> getAttribute( 'defer' ) !== false ) && !( $item -> hasAttribute( 'async' ) && $item -> getAttribute( 'async' ) !== false ) && $src )
			{
				if( $bGrpExcl )
					array_splice( $contGroup[ 1 ], count( $contGroup[ 1 ] ), 0, array( $item, '' ) );

				$contGroup[ 1 ][ count( $contGroup[ 1 ] ) - 1 ] .= $cont;
			}
			else
			{
				if( $bGrpExcl )
				{
					array_splice( $contGroup[ 1 ], $contGroup[ 0 ][ 0 ], 1, array( substr( $contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ], 0, $contGroup[ 0 ][ 1 ] ), $item, substr( $contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ], $contGroup[ 0 ][ 1 ] ) ) );
					$contGroup[ 0 ][ 0 ] += 2;
					$contGroup[ 0 ][ 1 ] = 0;
				}

				$contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ] = substr_replace( $contGroup[ 1 ][ $contGroup[ 0 ][ 0 ] ], $cont, $contGroup[ 0 ][ 1 ], 0 );
				$contGroup[ 0 ][ 1 ] += strlen( $cont );
			}

			unset( $contGroup );

			$item -> parentNode -> removeChild( $item );
		}
		else if( $delay && (isset($settJs[ 'preLoadEarly' ])?$settJs[ 'preLoadEarly' ]:null) )
			Script_SrcAddPreloading( $item, $src, $head, $doc );
	}

	if( $optLoad )
	{
		foreach( HtmlNd::ChildrenAsArr( $doc -> getElementsByTagName( 'iframe' ) ) as $item )
		{
			if( ContentProcess_IsAborted( $settCache ) ) return( true );

			if( HtmlNd::FindUpByTag( $item, 'noscript' ) )
				continue;

			if( !Scripts_IsElemAs( $ctxProcess, $doc, $settJs, $item ) )
				continue;

			$src = HtmlNd::GetAttrVal( $item, 'src' );
			$id = HtmlNd::GetAttrVal( $item, 'id' );
			$srcInfo = GetSrcAttrInfo( $ctxProcess, null, null, $src );

			$detectedPattern = null;
			$isCrit = GetObjSrcCritStatus( $settNonCrit, $specs, $srcInfo, $src, $id, null, $detectedPattern );

			Fullness_AdjustUrl( $ctxProcess, $src, (isset($srcInfo[ 'srcUrlFullness' ])?$srcInfo[ 'srcUrlFullness' ]:null) );
			$item -> setAttribute( 'src', $src );
			$item -> setAttribute( 'async', '' );

			if( (isset($ctxProcess[ 'debug' ])?$ctxProcess[ 'debug' ]:null) )
				$item -> setAttribute( 'seraph-accel-debug', 'status=' . ( $isCrit === true ? 'critical' : ( $isCrit === null ? 'special' : 'nonCritical' ) ) . ';' . ( $detectedPattern ? ' detectedPattern="' . $detectedPattern . '"' : '' ) );

			if( $isCrit )
				continue;

			$delay = ( $isCrit === null ) ? $specsDelayTimeout : $notCritsDelayTimeout;
			if( !$delay )
				continue;

			HtmlNd::RenameAttr( $item, 'src', 'data-src' );
			if( $isCrit === null )
			{
				$item -> setAttribute( 'type', 'o/js-lzls' );
				$delaySpecNeeded = true;
			}
			else
			{
				$item -> setAttribute( 'type', 'o/js-lzl' );
				$delayNotCritNeeded = true;
			}
		}
	}

	foreach( $contGroups as $contGroupId => $contGroup )
	{
		foreach( $contGroup[ 1 ] as $cont )
		{
			if( !$cont )
				continue;

			if( is_string( $cont ) )
			{
				$item = $doc -> createElement( 'script' );
				if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
					$item -> setAttribute( $item, 'type', 'text/javascript' );

				if( !GetContentProcessorForce( $sett ) && (isset($ctxProcess[ 'chunksEnabled' ])?$ctxProcess[ 'chunksEnabled' ]:null) && Gen::GetArrField( $settCache, array( 'chunks', 'js' ) ) )
				{
					$idSub = ( string )( $ctxProcess[ 'subCurIdx' ]++ ) . '.js';
					$ctxProcess[ 'subs' ][ $idSub ] = $cont;
					$src = ContentProcess_GetGetPartUri( $ctxProcess, $idSub );
				}
				else
				{
					$cont = str_replace( ContentMarkGetSep(), '', $cont );
					if( !adkxsshiujqtfk( $ctxProcess, $settCache, 'js', $cont, $src ) )
						return( false );
				}

				Cdn_AdjustUrl( $ctxProcess, $settCdn, $src, 'js' );
				Fullness_AdjustUrl( $ctxProcess, $src );
				$item -> setAttribute( 'src', $src );
			}
			else
				$item = $cont;

			if( $contGroupId === 'crit' )
			{
				$head -> insertBefore( $item, $head -> firstChild );
				continue;
			}

			if( is_string( $cont ) && $optLoad )
			{
				$delay = ( $contGroupId === 'spec' ) ? $specsDelayTimeout : $notCritsDelayTimeout;
				if( $delay )
				{

					if( $contGroupId === 'spec' )
					{
						$item -> setAttribute( 'type', 'o/js-lzls' );
						$delaySpecNeeded = true;

						$delay = $specsDelayTimeout;
					}
					else
					{
						$item -> setAttribute( 'type', 'o/js-lzl' );
						$delayNotCritNeeded = true;

						$delay = $notCritsDelayTimeout;
					}

					if( (isset($settJs[ 'preLoadEarly' ])?$settJs[ 'preLoadEarly' ]:null) )
						Script_SrcAddPreloading( $item, $src, $head, $doc );
				}
			}

			$body -> appendChild( $item );
		}
	}

	if( $delayNotCritNeeded || $delaySpecNeeded )
	{

		{
			$item = $doc -> createElement( 'script' );
			if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
				$item -> setAttribute( 'type', 'text/javascript' );

			$item -> nodeValue = htmlspecialchars( '
				(
					function( d )
					{
						function SetSize( e )
						{
							e.style.setProperty("--seraph-accel-client-width", "" + e.clientWidth + "px");
							e.style.setProperty("--seraph-accel-client-width-px", "" + e.clientWidth);
							e.style.setProperty("--seraph-accel-client-height", "" + e.clientHeight + "px");
						}

						d.addEventListener( "seraph_accel_calcSizes", function( evt ) { SetSize( d.documentElement ); }, { capture: true, passive: true } );
						SetSize( d.documentElement );
					}
				)( document );
			' );

			$body -> insertBefore( $item, $body -> firstChild );
		}

		$delayCss = false;
		if( $notCritsDelayTimeout && (isset($ctxProcess[ 'lazyloadStyles' ][ 'nonCrit' ])?$ctxProcess[ 'lazyloadStyles' ][ 'nonCrit' ]:null) === 'withScripts' )
		{
			$delayCss = true;
			unset( $ctxProcess[ 'lazyloadStyles' ][ 'nonCrit' ] );
		}

		$ctxProcess[ 'jsDelay' ] = array( 'a' => array( '_E_A1_', '_E_A2_', '_E_TM1_', '_E_TM2_', '_E_CSS_', '_E_CJSD_', '_E_AD_', '_E_FCD_', '_E_PRL_', '_E_LF_' ), 'v' => array( '"o/js-lzl"', '"o/js-lzls"', $notCritsDelayTimeout ? $notCritsDelayTimeout : 0, $specsDelayTimeout ? $specsDelayTimeout : 0, $delayCss ? $delayCss : 0, (isset($settJs[ 'cplxDelay' ])?$settJs[ 'cplxDelay' ]:null) ? 1 : 0, Gen::GetArrField( $settJs, array( 'aniDelay' ), 250 ), Gen::GetArrField( $settJs, array( 'clk', 'delay' ), 250 ), (isset($settJs[ 'preLoadEarly' ])?$settJs[ 'preLoadEarly' ]:null) ? 0 : 1, (isset($settJs[ 'loadFast' ])?$settJs[ 'loadFast' ]:null) ? 1 : 0 ) );

	}

	return( true );
}

function Scripts_ProcessAddRtn( &$ctxProcess, $sett, $settCache, $settContPr, $settJs, $settCdn, $doc, $prms )
{

	$cont = str_replace( $prms[ 'a' ], $prms[ 'v' ], "(function(t,n,u,H,D,r,C,L,M,N,O,P,Q){function I(){if(v){var g=t[function(k){var a=\"\";k.forEach(function(e){a+=String.fromCharCode(e+3)});return a}([103,78,114,98,111,118])];!v.dkhjihyvjed&&g?v=void 0:(v.dkhjihyvjed=!0,v.jydy(g))}}function A(g,k=0,a){function e(){if(!g)return[];for(var c=[].slice.call(n.querySelectorAll('[type=\"'+g+'\"]')),f=0,b=c.length;f<b;f++){var h=c[f];!h.hasAttribute(\"defer\")||!1===h.defer||h.hasAttribute(\"async\")&&!1!==h.async||!h.hasAttribute(\"src\")||(c.splice(f,1),c.push(h),\nf--,b--)}return c}function l(c=!1){I();Q||c?m():u(m,k)}function d(c){c=c.ownerDocument;var f=c.seraph_accel_njsujyhmaeex={hujvqjdes:\"\",wyheujyhm:c[function(b){var h=\"\";b.forEach(function(p){h+=String.fromCharCode(p+3)});return h}([116,111,102,113,98])],wyhedbujyhm:c[function(b){var h=\"\";b.forEach(function(p){h+=String.fromCharCode(p+3)});return h}([116,111,102,113,98,105,107])],ujyhm:function(b){this.seraph_accel_njsujyhmaeex.hujvqjdes+=b},dbujyhm:function(b){this.write(b+\"\\n\")}};c[function(b){var h=\n\"\";b.forEach(function(p){h+=String.fromCharCode(p+3)});return h}([116,111,102,113,98])]=f.ujyhm;c[function(b){var h=\"\";b.forEach(function(p){h+=String.fromCharCode(p+3)});return h}([116,111,102,113,98,105,107])]=f.dbujyhm}function q(c){var f=c.ownerDocument,b=f.seraph_accel_njsujyhmaeex;if(b){if(b.hujvqjdes){var h=f.createElement(\"span\");c.parentNode.insertBefore(h,c.nextSibling);h.outerHTML=b.hujvqjdes}f[function(p){var x=\"\";p.forEach(function(E){x+=String.fromCharCode(E+3)});return x}([116,111,\n102,113,98])]=b.wyheujyhm;f[function(p){var x=\"\";p.forEach(function(E){x+=String.fromCharCode(E+3)});return x}([116,111,102,113,98,105,107])]=b.wyhedbujyhm;delete f.seraph_accel_njsujyhmaeex}}function m(){var c=y.shift();if(c)if(c.parentNode){var f=n.seraph_accel_usbpb(c.tagName),b=c.attributes;if(b)for(var h=0;h<b.length;h++){var p=b[h],x=p.value;p=p.name;\"type\"!=p&&(\"data-type\"==p&&(p=\"type\"),\"data-src\"==p&&(p=\"src\"),f.setAttribute(p,x))}f.textContent=c.textContent;b=!f.hasAttribute(\"async\");h=\nf.hasAttribute(\"src\");p=f.hasAttribute(\"nomodule\");b&&d(f);if(h=b&&h&&!p)f.onload=f.onerror=function(){f._seraph_accel_loaded||(f._seraph_accel_loaded=!0,q(f),l())};c.parentNode.replaceChild(f,c);h||(b&&q(f),l(!b))}else y=e(),m();else a&&a()}var y=e();if(P){var B=n.createDocumentFragment();y.forEach(function(c){var f=c?c.getAttribute(\"src\"):void 0;if(f){var b=n.createElement(\"link\");b.setAttribute(\"rel\",\"preload\");b.setAttribute(\"as\",\"IFRAME\"==c.tagName?\"document\":\"script\");b.setAttribute(\"href\",\nf);c.hasAttribute(\"integrity\")&&b.setAttribute(\"integrity\",c.getAttribute(\"integrity\"));c.hasAttribute(\"crossorigin\")&&b.setAttribute(\"crossorigin\",c.getAttribute(\"crossorigin\"));B.appendChild(b)}});n.head.appendChild(B)}l()}function w(g,k,a){var e=n.createEvent(\"Events\");e.initEvent(k,!0,!1);if(a)for(var l in a)e[l]=a[l];g.dispatchEvent(e)}function F(g,k){function a(l){try{Object.defineProperty(n,\"readyState\",{configurable:!0,enumerable:!0,value:l})}catch(d){}}function e(l){r?(v&&(v.jydyut(),v=void 0),\na(\"interactive\"),w(n,\"readystatechange\"),w(n,\"DOMContentLoaded\"),delete n.readyState,w(n,\"readystatechange\"),u(function(){w(t,\"load\");w(t,\"scroll\");k&&k();l()})):l()}if(z){if(3==z){function l(){r&&a(\"loading\");g?A(r?H:0,10,function(){e(function(){2==z?(z=1,1E6!=C&&u(function(){F(!0)},C)):A(D)})}):A(r?H:0,0,function(){e(function(){A(D)})})}function d(){for(var q,m;void 0!==(q=Object.keys(seraph_accel_izrbpb.a)[0]);){for(;m=seraph_accel_izrbpb.a[q].shift();)if(m(d))return;delete seraph_accel_izrbpb.a[q]}l()}\nL&&function(q,m){q.querySelectorAll(m).forEach(function(y){var B=y.cloneNode();B.rel=\"stylesheet\";y.parentNode.replaceChild(B,y)})}(n,'link[rel=\"stylesheet/lzl-nc\"]');d()}else 1==z&&A(D);g?z--:z=0}}function J(g){function k(d){return\"click\"==d||\"touchend\"==d}function a(d){if(k(d.type)){if(void 0!==l){var q=!0;if(\"click\"==d.type)for(var m=d.target;m;m=m.parentNode)if(m.getAttribute&&(m.getAttribute(\"data-lzl-clk-no\")&&(q=!1),m.getAttribute(\"data-lzl-clk-nodef\"))){d.preventDefault();break}if(q){q=!1;\nfor(m=0;m<l.length;m++)if(l[m].type==d.type){q=!0;break}q||l.push(d)}}}else n.removeEventListener(d.type,a,{passive:!0});F(!1,e)}function e(){K.forEach(function(d){n.removeEventListener(d,a,{passive:!k(d)})});u(function(){n.body.classList.remove(\"seraph-accel-js-lzl-ing\");l.forEach(function(d){if(\"touchend\"==d.type){var q=d.changedTouches&&d.changedTouches.length?d.changedTouches[0]:void 0,m=q?n.elementFromPoint(q.clientX,q.clientY):void 0;m&&(w(m,\"touchstart\",{touches:[{clientX:q.clientX,clientY:q.clientY}],\nchangedTouches:d.changedTouches}),w(m,\"touchend\",{touches:[{clientX:q.clientX,clientY:q.clientY}],changedTouches:d.changedTouches}))}else\"click\"==d.type&&(m=n.elementFromPoint(d.clientX,d.clientY))&&m.dispatchEvent(new MouseEvent(\"click\",{view:d.view,bubbles:!0,cancelable:!0,clientX:d.clientX,clientY:d.clientY}))});l=void 0},O);u(function(){n.body.classList.remove(\"seraph-accel-js-lzl-ing-ani\")},N)}g.currentTarget&&g.currentTarget.removeEventListener(g.type,J);var l=[];1E6!=r&&u(function(){F(!0,e)},\nr);K.forEach(function(d){n.addEventListener(d,a,{passive:!k(d)})})}function G(){u(function(){w(n,\"seraph_accel_calcSizes\")},0)}t.location.hash.length&&(r&&(r=1),C&&(C=1));r&&u(function(){n.body.classList.add(\"seraph-accel-js-lzl-ing-ani\")});var K=\"scroll wheel mouseenter mousemove mouseover keydown click touchstart touchmove touchend\".split(\" \"),v=M?{a:[],jydy:function(g){if(g&&g.fn&&!g.seraph_accel_bpb){this.a.push(g);g.seraph_accel_bpb={otquhdv:g.fn[function(k){var a=\"\";k.forEach(function(e){a+=\nString.fromCharCode(e+3)});return a}([111,98,94,97,118])]};if(g[function(k){var a=\"\";k.forEach(function(e){a+=String.fromCharCode(e+3)});return a}([101,108,105,97,79,98,94,97,118])])g[function(k){var a=\"\";k.forEach(function(e){a+=String.fromCharCode(e+3)});return a}([101,108,105,97,79,98,94,97,118])](!0);g.fn[function(k){var a=\"\";k.forEach(function(e){a+=String.fromCharCode(e+3)});return a}([111,98,94,97,118])]=function(k){n.addEventListener(\"DOMContentLoaded\",function(a){k.bind(n)(g,a)});return this}}},\njydyut:function(){for(var g=0;g<this.a.length;g++){var k=this.a[g];k.fn[function(a){var e=\"\";a.forEach(function(l){e+=String.fromCharCode(l+3)});return e}([111,98,94,97,118])]=k.seraph_accel_bpb.otquhdv;delete k.seraph_accel_bpb;if(k[function(a){var e=\"\";a.forEach(function(l){e+=String.fromCharCode(l+3)});return e}([101,108,105,97,79,98,94,97,118])])k[function(a){var e=\"\";a.forEach(function(l){e+=String.fromCharCode(l+3)});return e}([101,108,105,97,79,98,94,97,118])](!1)}}}:void 0;t.seraph_accel_gzjydy=\nI;var z=3;t.addEventListener(\"load\",J);t.addEventListener(\"resize\",G,!1);n.addEventListener(\"DOMContentLoaded\",G,!1);t.addEventListener(\"load\",G)})(window,document,setTimeout,_E_A1_,_E_A2_,_E_TM1_,_E_TM2_,_E_CSS_,_E_CJSD_,_E_AD_,_E_FCD_,_E_PRL_,_E_LF_)" );

	$item = $doc -> createElement( 'script' );
	if( apply_filters( 'seraph_accel_jscss_addtype', false ) )
		$item -> setAttribute( 'type', 'text/javascript' );

	$item -> setAttribute( 'id', 'seraph-accel-js-lzl' );

	HtmlNd::SetValFromContent( $item, $cont );

	$ctxProcess[ 'ndBody' ] -> appendChild( $item );

	ContentMarkSeparate( $item );

}

function Scripts_IsElemAs( &$ctxProcess, $doc, $settJs, $item )
{
	$items = &$ctxProcess[ 'scriptsInclItems' ];
	if( $items === null )
	{
		$items = array();

		$incls = Gen::GetArrField( $settJs, array( 'other', 'incl' ), array() );
		if( $incls )
		{
			$xpath = new \DOMXPath( $doc );

			foreach( $incls as $inclItemPath )
				foreach( HtmlNd::ChildrenAsArr( $xpath -> query( $inclItemPath, $ctxProcess[ 'ndHtml' ] ) ) as $itemIncl )
					$items[] = $itemIncl;
		}
	}

	return( in_array( $item, $items, true ) );
}

function JsMinify( $cont, $method, $removeFlaggedComments = false )
{
	try
	{
		switch( $method )
		{
		case 'jshrink':		$contNew = JShrink\Minifier::minify( $cont, array( 'flaggedComments' => !$removeFlaggedComments ) ); break;
		default:			$contNew = JSMin\JSMin::minify( $cont, array( 'removeFlaggedComments' => $removeFlaggedComments ) ); break;
		}
	}
	catch( \Exception $e )
	{
		return( $cont );
	}

	if( !$contNew )
		return( $cont );

	$cont = $contNew;

	if( (isset($ctxProcess[ 'debug' ])?$ctxProcess[ 'debug' ]:null) )
		$cont = '/* DEBUG: MINIFIED by seraph-accel */' . $cont;

	return( $cont );
}

function Script_AdjustCont( $ctxProcess, $settCache, $settJs, $srcInfo, $src, $id, &$cont )
{
	if( !$cont )
		return( false );

	$adjusted = false;
	if( ( !$srcInfo || !(isset($srcInfo[ 'ext' ])?$srcInfo[ 'ext' ]:null) ) && Gen::GetArrField( $settJs, array( 'min' ), false ) && !IsObjInRegexpList( Gen::GetArrField( $settJs, array( 'minExcls' ), array() ), array( 'src' => $src, 'id' => $id, 'body' => $cont ) ) )
	{
		$contNew = trim( JsMinify( $cont, (isset($settJs[ 'minMthd' ])?$settJs[ 'minMthd' ]:null), (isset($settJs[ 'cprRem' ])?$settJs[ 'cprRem' ]:null) ) );
		if( $cont != $contNew )
		{
			$cont = $contNew;
			$adjusted = true;
		}
	}

	return( $adjusted );
}

