<?php

/**
 * A PHPUnit TestListener that exposes your slowest running tests by outputting
 * results directly to the console.
 */
class TextMexListener implements PHPUnit_Framework_TestListener {
	/**
	 * Internal tracking for test suites.
	 *
	 * Increments as more suites are run, then decremented as they finish. All
	 * suites have been run when returns to 0.
	 *
	 * @var integer
	 */
	protected $suites = 0;

	/**
	 * Number of tests to report on for slowness.
	 *
	 * @var int
	 */
	protected $reportLength;

	/**
	 * Collection of tests results, is classed as document in Elaestic Search
	 *
	 * @var array
	 */
	protected $results = array();

	/**
	 * If see will send metric even when run locally
	 *
	 * @var bool
	 */
	protected $debug = true;

	protected $http;

	protected $http_uri = '';


	/**
	 * Construct a new instance.
	 *
	 * //TODO this DI for a class that sends to text-mex.
	 *
	 * @param array $options
	 */
	public function __construct( array $options = array() ) {
		$this->loadOptions( $options );

		$this->http = $client = new GuzzleHttp\Client(['base_uri' => 'http://192.168.1.98:8080','auth' => ['admin', 'test']]);
	}

	/**
	 * An error occurred.
	 *
	 * @param PHPUnit_Framework_Test $test
	 * @param Exception $e
	 * @param float $time
	 */
	public function addError( PHPUnit_Framework_Test $test, Exception $e, $time ) {
		$this->setTestResult( $test, 'Error' );
	}

	/**
	 * A warning occurred.
	 *
	 * @param PHPUnit_Framework_Test $test
	 * @param PHPUnit_Framework_Warning $e
	 * @param float $time
	 *
	 * @since Method available since Release 5.1.0
	 */
	public function addWarning( PHPUnit_Framework_Test $test, PHPUnit_Framework_Warning $e, $time ) {
	}

	/**
	 * A failure occurred.
	 *
	 * @param PHPUnit_Framework_Test $test
	 * @param PHPUnit_Framework_AssertionFailedError $e
	 * @param float $time
	 */
	public function addFailure( PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time ) {

		echo('ddd');
		$this->setTestResult( $test, 'Fail' );
	}

	/**
	 * Incomplete test.
	 *
	 * @param PHPUnit_Framework_Test $test
	 * @param Exception $e
	 * @param float $time
	 */
	public function addIncompleteTest( PHPUnit_Framework_Test $test, Exception $e, $time ) {
	}

	/**
	 * Risky test.
	 *
	 * @param PHPUnit_Framework_Test $test
	 * @param Exception $e
	 * @param float $time
	 *
	 * @since  Method available since Release 4.0.0
	 */
	public function addRiskyTest( PHPUnit_Framework_Test $test, Exception $e, $time ) {
	}

	/**
	 * Skipped test.
	 *
	 * @param PHPUnit_Framework_Test $test
	 * @param Exception $e
	 * @param float $time
	 */
	public function addSkippedTest( PHPUnit_Framework_Test $test, Exception $e, $time ) {
	}

	/**
	 * A test started.
	 *
	 * @param PHPUnit_Framework_Test $test
	 */
	public function startTest( PHPUnit_Framework_Test $test ) {
	}

	/**
	 * A test ended.
	 *
	 * @param PHPUnit_Framework_Test $test
	 * @param float $time
	 */
	public function endTest( PHPUnit_Framework_Test $test, $time ) {
		if ( ! $test instanceof PHPUnit_Framework_TestCase ) {
			return;
		}

		$time      = $this->toMilliseconds( $time );

		$this->addCompletedTest( $test, $time );
		$this->setTestResult( $test );

	}

	/**
	 * A test suite started.
	 *
	 * @param PHPUnit_Framework_TestSuite $suite
	 */
	public function startTestSuite( PHPUnit_Framework_TestSuite $suite ) {
		$this->suites ++;
	}

	/**
	 * A test suite ended.
	 *
	 * @param PHPUnit_Framework_TestSuite $suite
	 */
	public function endTestSuite( PHPUnit_Framework_TestSuite $suite ) {
		$this->suites --;

		if ( 0 === $this->suites && ! empty( $this->results ) ) {

			$this->renderHeader();
			$this->submitResults();
			$this->renderBody();
		}
	}

	/**
	 * Adds a completed test to the document store
	 *
	 * @param PHPUnit_Framework_TestCase $test
	 * @param int $time Test execution time in milliseconds
	 */
	protected function addCompletedTest( PHPUnit_Framework_Test $test, $time ) {
		$this->results[$test->getName()] = $this->getTestResult( $test, $time );
	}

	/**
	 * Convert PHPUnit's reported test time (microseconds) to milliseconds.
	 *
	 * @param float $time
	 *
	 * @return int
	 */
	protected function toMilliseconds( $time ) {
		return (int) round( $time * 1000 );
	}

	/**
	 * Label for describing a test.
	 *
	 * @param PHPUnit_Framework_TestCase $test
	 *
	 * @return string
	 */
	protected function getTestResult( PHPUnit_Framework_TestCase $test, string $time ) : array {

		$ann = $test->getAnnotations();

		//todo account for datasets
		return [
			'name'             => $test->getName(),
			'result'           => $test->getResult(),
			'time_taken'       => [
				'result' => $time,
				'format' => 'ms',
			],
			'covers'           => isset( $ann['method']['covers'] ) ? $ann['method']['covers'] : '',
		];

	}

	/**
	 * @param PHPUnit_Framework_Test $test
	 * @param string $result
	 */
	protected function setTestResult( PHPUnit_Framework_Test $test, $result = 'pass' ) {
		if ( ! isset( $this->results[ $test->getName() ] ) ) {
			return;
		}

		if ( ! empty( $this->results[ $test->getName() ]['result'] ) ) {
			return;
		}

		$this->results[ $test->getName() ]['result'] = $result;
	}

	/**
	 * Checks to see if this is running on travis, as we only want unit tests run on travis.
	 * Can be shorted via debug.
	 *
	 * @return bool
	 */
	protected function isTravis() : bool {

	}

	/**
	 * Calculate number of  tests to report about.
	 *
	 * @return int
	 */
	protected function getReportLength() : int {
		return count( $this->results );
	}

	/**
	 * Renders slow test report header.
	 */
	protected function renderHeader() {
		echo 'Submitting Result to Text-Mex';
	}

	/**
	 * Renders slow test report body.
	 */
	protected function renderBody() {

		print_R($this->setTestSuiteReport());

	}


	/**
	 * Populate options into class internals.
	 *
	 * @param array $options
	 */
	protected function loadOptions( array $options ) {

	}

	/**
	 * @return array
	 */
	protected function getTestSuiteDetails(){
		$suite = [
			'branch_name'      => getenv( 'TRAVIS_PULL_REQUEST_BRANCH' ),
			'branch_tree_hash' => '',
		];
		return $suite;
	}

	/**
	 * @return array
	 */
	protected function setTestSuiteReport() : array {

		$result = $this->getTestSuiteDetails();

		$result['results'] = $this->results;

		return $result;

	}

	/**
	 *
	 */
	protected function submitResults(){

		var_dump( $this->setTestSuiteReport() );

		$response = $this->http->request('PUT','/result', [
			'json' => $this->setTestSuiteReport()
		]);

		return $response;

	}

	/**
	 * <code>
	 * @excludeResult
	 * public function testLongRunningProcess() {}
	 * </code>
	 *
	 * @param PHPUnit_Framework_TestCase $test
	 *
	 * @return bool
	 */
	protected function getExcludeResult( PHPUnit_Framework_TestCase $test ) : bool {
		$ann = $test->getAnnotations();
		return isset( $ann['method']['excludeResult'][0] );
	}
}
