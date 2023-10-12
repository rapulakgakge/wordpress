<?php

namespace seraph_accel\Sabberworm\CSS\CSSList;

use seraph_accel\Sabberworm\CSS\Property\AtRule;

/**
 * A BlockList constructed by an unknown @-rule. @media rules are rendered into AtRuleBlockList objects.
 */
class AtRuleBlockList extends CSSBlockList implements AtRule {

	private $sType;
	private $sArgs;

	public function __construct($sType, $sArgs = '', $iPos = 0) {
		parent::__construct($iPos);
		$this->sType = $sType;
		$this->sArgs = $sArgs;
	}

	public function atRuleName() {
		return $this->sType;
	}

	public function atRuleArgs() {
		return $this->sArgs;
	}

	public function setAtRuleArgs($sArgs) {
		$this->sArgs = $sArgs;
	}

	public function __toString() {
		return $this->renderWhole(new \seraph_accel\Sabberworm\CSS\OutputFormat());
	}

	public function render(string &$sResult, \seraph_accel\Sabberworm\CSS\OutputFormat $oOutputFormat) {
		$sResult .= $oOutputFormat->sBeforeAtRuleBlock;
		$sResult .= '@';
		$sResult .= $this->sType;
		if ($this->sArgs) {
			$sResult .= ' ';
			$sResult .= $this->sArgs;
		}
		$sResult .= $oOutputFormat->spaceBeforeOpeningBrace();
		$sResult .= '{';
		parent::render($sResult, $oOutputFormat);
		$sResult .= '}';
		$sResult .= $oOutputFormat->sAfterAtRuleBlock;
	}

	public function isRootList() {
		return false;
	}

}