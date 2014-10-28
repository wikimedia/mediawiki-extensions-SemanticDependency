<?php

/*
 * Copyright (c) 2014 The MITRE Corporation
 *
 * Permission is hereby granted, free of charge, to any person obtaining a
 * copy of this software and associated documentation files (the "Software"),
 * to deal in the Software without restriction, including without limitation
 * the rights to use, copy, modify, merge, publish, distribute, sublicense,
 * and/or sell copies of the Software, and to permit persons to whom the
 * Software is furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

class SemanticDependency {

	/**
	 * @since 1.0
	 *
	 * @param Parser &$parser
	 */
	public static function setup( &$parser ) {

		if ( !isset( $GLOBALS['SemanticDependency_Properties'] ) ) {
			$GLOBALS['SemanticDependency_Properties'] = array();
		}

		if ( !isset( $GLOBALS['SemanticDependency_JobThreshold'] ) ) {
			$GLOBALS['SemanticDependency_JobThreshold'] = 1;
		}

		return true;

	}

	/**
	 * @since 1.0
	 *
	 * @param Store $store
	 * @param SemanticData $semanticData
	 */
	public static function updateDataAfter( \SMW\Store $store,
		\SMW\SemanticData $semanticData ) {

		$instance = new self( $store, $semanticData );
		$instance->setConfiguration(
			$GLOBALS['SemanticDependency_Properties'],
			$GLOBALS['SemanticDependency_JobThreshold'] );
		$instance->performUpdate();

		return true;
	}

	private $store;
	private $semanticData;
	private $properties;
	private $jobThreshold;

	/**
	 * @since 1.0
	 *
	 * @param Store $store
	 * @param SemanticData $semanticData
	 */
	public function __construct( \SMW\Store $store,
		\SMW\SemanticData $semanticData ) {
		$this->store = $store;
		$this->semanticData = $semanticData;
	}

	/**
	 * @since 1.0
	 *
	 * @param array $properties
	 * @param $jobThreshold
	 */
	public function setConfiguration( array $properties, $jobThreshold ) {
		$this->properties = $properties;
		$this->jobThreshold = $jobThreshold;
	}

	/**
	 * @since 1.0
	 */
	public function performUpdate() {

		$title = $this->semanticData->getSubject()->getTitle();

		$dependentTitles = $this->getDependentTitles( $title );

		$jobs = array();

		$count = 1;
		foreach ( $dependentTitles as $dependentTitle ) {
			$job = new SMWUpdateJob( $dependentTitle );
			if ( $count > $this->jobThreshold ) {
				$jobs[] = $job;
			} else {
				$job->run();
			}
			$count++;
		}

		if ( $jobs !== array() ) {
			Job::batchInsert( $jobs );
		}

	}

	private function getDependentTitles( Title $title ) {

		$dataItems = $this->getFilteredPropertyValuesByNamespace( $title );

		$dependentTitles = array();

		foreach ( $dataItems as $dataItem ) {

			if ( $dataItem->getDIType() === SMWDataItem::TYPE_WIKIPAGE ) {

				$dependentTitle = $dataItem->getTitle();

				// do not allow dependent pages that themselves
				// have dependent pages in order to avoid an infinite loop
				if ( $this->getFilteredPropertyValuesByNamespace(
					$dependentTitle ) === array() ) {

					$dependentTitles[] = $dependentTitle;

				}

			}

		}

		return $dependentTitles;
	}

	private function getFilteredPropertyValuesByNamespace( Title $title ) {

		$dataItems = array();

		$namespace = $title->getNamespace();

		if ( array_key_exists( $namespace, $this->properties ) ) {

			$property = $this->properties[$namespace];

			$page = SMWDIWikiPage::newFromTitle( $title );

			$semanticData = $this->store->getSemanticData( $page );
			$dataItems = $semanticData->getPropertyValues(
				SMWDIProperty::newFromUserLabel( $property ) );

		}

		return $dataItems;

	}

}
