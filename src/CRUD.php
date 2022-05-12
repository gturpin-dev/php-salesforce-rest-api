<?php

namespace bjsmasth\Salesforce;

use GuzzleHttp\Client;

class CRUD
{
    protected $instance_url;
    protected $access_token;
	private   $api_version;

    public function __construct()
    {
        if (!isset($_SESSION) and !isset($_SESSION['salesforce'])) {
            throw new \Exception('Access Denied', 403);
        }

        $this->instance_url = $_SESSION['salesforce']['instance_url'];
        $this->access_token = $_SESSION['salesforce']['access_token'];
        $this->api_version  = 'v52.0';
    }

	public function getApiVersion() {
		return $this->api_version;
	}

	public function setApiVersion(string $api_version) {
		$this->api_version = $api_version;
	}

    public function query($query)
    {
        $url = "$this->instance_url/services/data/$this->api_version/query";

        $client = new Client();
        $request = $client->request('GET', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token"
            ],
            'query' => [
                'q' => $query
            ]
        ]);

        return json_decode($request->getBody(), true);
    }
	
	/**
	 * Duplicate of query, but handle max limit of query
	 *
	 * @param string $query Query to execute
	 *
	 * @return array of records
	 */
    public function query_merge_limit( $query ) {
        $url = "$this->instance_url/services/data/$this->api_version/query";

        $client = new Client();
        $request = $client->request( 'GET', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token"
            ],
            'query' => [
                'q' => $query
            ]
        ] );

		$response = json_decode( $request->getBody(), true );

		// there is more than one request to get all records
		if ( isset( $response['nextRecordsUrl'] ) && ! empty( $response['nextRecordsUrl'] ) ) {
			$records          = $response['records'];
			$current_response = $response;

			// get all records and merge them until there is no more records
			while ( isset( $current_response['nextRecordsUrl'] ) ) {
				$next_url        = $current_response['nextRecordsUrl'];
				$url             = $this->instance_url . $next_url;
	
				$request = $client->request( 'GET', $url, [
					'headers' => [
						'Authorization' => "OAuth $this->access_token"
					],
					'query' => [
						'q' => $query
					]
				] );
		
				$response = json_decode( $request->getBody(), true );

				if ( isset( $response['records'] ) ) {
					$current_response = $response;
					$records          = array_merge( $records, $response['records'] );
				} else {
					return $response;
				}
			}

			return $records;

		} else {
			return $response;
		}
    }

    public function create($object, array $data)
    {
        $url = "$this->instance_url/services/data/$this->api_version/sobjects/$object/";

        $client = new Client();

        $request = $client->request('POST', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token",
                'Content-type' => 'application/json'
            ],
            'json' => $data
        ]);

        $status = $request->getStatusCode();

        if ($status != 201) {
            die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
        }

        $response = json_decode($request->getBody(), true);
        $id = $response["id"];

        return $id;

    }

    public function update($object, $id, array $data)
    {
        $url = "$this->instance_url/services/data/$this->api_version/sobjects/$object/$id";

        $client = new Client();

        $request = $client->request('PATCH', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token",
                'Content-type' => 'application/json'
            ],
            'json' => $data
        ]);

        $status = $request->getStatusCode();

        if ($status != 204) {
            die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
        }

        return $status;
    }

    public function delete($object, $id)
    {
        $url = "$this->instance_url/services/data/$this->api_version/sobjects/$object/$id";

        $client = new Client();
        $request = $client->request('DELETE', $url, [
            'headers' => [
                'Authorization' => "OAuth $this->access_token",
            ]
        ]);

        $status = $request->getStatusCode();

        if ($status != 204) {
            die("Error: call to URL $url failed with status $status, response: " . $request->getReasonPhrase());
        }

        return true;
    }
}
