<?php

require_once DIR_SYSTEM . 'library/sameday-php-sdk/src/Sameday/autoload.php';

class ModelExtensionShippingSameday extends Model
{
    /**
     * @var SamedayHelper
     */
    private $samedayHelper;

    const SAMEDAY_CONFIGS = [
        'username',
        'password',
        'testing',
        'host_country',
    ];

    public function __construct($registry)
    {
        parent::__construct($registry);

        $this->samedayHelper = Samedayclasses::getSamedayHelper($this->buildRequest(self::SAMEDAY_CONFIGS), $registry, $this->getPrefix());
    }

    /**
     * @param array $address
     *
     * @return array
     */
    public function getQuote($address)
    {
        $query = $this->db->query('SELECT * FROM ' . DB_PREFIX . "zone_to_geo_zone WHERE 
            geo_zone_id='{$this->getConfig('sameday_geo_zone_id')}'
            AND country_id='{$address['country_id']}'
            AND (zone_id='{$address['zone_id']}' OR zone_id='0')");

        if (!$this->getConfig('sameday_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }

        $this->load->language('extension/shipping/sameday');

        $method_data = array();
        if (!$status) {
            return $method_data;
        }

        $isEstimatedCostEnabled = $this->getConfig('sameday_estimated_cost');

        $availableService = $this->getAvailableServices($this->getConfig('sameday_testing'));
        $lockerMaxItems = (int) $this->getConfig('sameday_locker_max_items');
        $quote_data = array();

        if (empty($availableService)) {
            return null;
        }

        foreach ($availableService as $service) {
            if ($service['sameday_code'] === "LS") {
                continue;
            }

            if ($service['sameday_code'] === "2H" && $address['zone'] !== "Bucuresti") {
                continue;
            }

            if ($service['sameday_code'] === "LN" && (count($this->cart->getProducts()) > $lockerMaxItems)) {
                continue;
            }

            $price = $service['price'];

            if ($service['price_free'] !== null && $this->cart->getSubTotal() >= $service['price_free']) {
                $price = 0;
            }

            if ($isEstimatedCostEnabled) {
                $estimatedCost = $this->estimateCost($address, $service['sameday_id']);
                if ($estimatedCost != null) {
                    $price = $estimatedCost;
                }
            }

            $quote_data[$service['sameday_code']] = array(
                'sameday_name' => $service['name'],
                'code' => 'sameday.' . $service['sameday_code'] . '.' . $service['sameday_id'],
                'title' => $service['name'],
                'cost' => $price,
                'tax_class_id' => $this->getConfig('sameday_tax_class_id'),
                'text' => $this->currency->format(
                    $this->tax->calculate(
                        $price,
                        $this->getConfig('sameday_tax_class_id'),
                        $this->getConfig('config_tax')
                    ),
                    $this->session->data['currency']
                )
            );

            if ($service['sameday_code'] === "LN") {
                $this->syncLockers();
            }
        }

        if (empty($quote_data)) {
            return null;
        }

        $method_data = array(
            'code' => 'sameday',
            'title' => 'Sameday',
            'quote' => $quote_data,
            'sort_order' => $this->getConfig('sameday_sort_order'),
            'error' => false
        );

        return $method_data;
    }

    private function buildRequest(array $keys)
    {
        $entries = array();
        foreach ($keys as $key) {
            $entries["sameday_$key"] = $this->request->post["{$this->getPrefix()}sameday_$key"] ?? $this->getConfig("sameday_$key");
        }

        return $entries;
    }

    private function syncLockers()
    {
        $key =  "{$this->getPrefix()}sameday_sync_lockers_ts";
        $time = time();

        if ($time >= ($this->getConfig($key) + 86400)) {
            $this->lockersRefresh();
        }
    }

    /**
     * @return bool
     * @throws \Sameday\Exceptions\SamedaySDKException
     */
    private function lockersRefresh()
    {
        $this->load->model('extension/shipping/sameday');
        $sameday = new \Sameday\Sameday($this->samedayHelper->initClient());

        $request = new Sameday\Requests\SamedayGetLockersRequest();

        try {
            $lockers = $sameday->getLockers($request)->getLockers();
        } catch (\Exception $exception) {
            return false;
        }

        $remoteLockers = [];
        foreach ($lockers as $lockerObject) {
            $locker = $this->getLockerSameday($lockerObject->getId(), $this->isTesting());
            if (!$locker) {
                $this->addLocker($lockerObject, $this->isTesting());
            } else {
                $this->updateLocker($lockerObject, $locker['id']);
            }

            $remoteLockers[] = $lockerObject->getId();
        }

        // Build array of local lockers.
        $localLockers = array_map(
            static function ($locker) {
                if (isset($locker['id'])) {
                    return array(
                        'id' => $locker['id'],
                        'sameday_id' => $locker['locker_id']
                    );
                }

                return false;
            },

            $this->getLockers()
        );

        // Delete local lockers that aren't present in remote lockers anymore.
        foreach ($localLockers as $localLocker) {
            if (!in_array($localLocker['sameday_id'], $remoteLockers)) {
                $this->deleteLocker($localLocker['id']);
            }
        }

        $this->updateLastSyncTimestamp();

        return true;
    }

    /**
     * @return void
     */
    private function updateLastSyncTimestamp()
    {
        $store_id = 0;
        $code = "{$this->getPrefix()}sameday";
        $key =  "{$this->getPrefix()}sameday_sync_lockers_ts";
        $time = time();

        $lastTimeSynced = $this->getConfig('sameday_sync_lockers_ts');

        if ($lastTimeSynced === null) {
            $value = $time;

            $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
        }

        $lastTs = $this->db->query("SELECT * FROM " . DB_PREFIX . "setting WHERE store_id = '" . (int)$store_id . "' AND `key` = '" .$this->db->escape($key) .  "'  AND `code` = '" . $this->db->escape($code) . "'")->row;
        $this->db->query('UPDATE '. DB_PREFIX ."setting SET value='{$this->db->escape($time)}' WHERE setting_id='{$this->db->escape($lastTs['setting_id'])}'");
    }

    /**
     * @return array
     */
    public function getLockers()
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_locker WHERE testing='{$this->db->escape($this->isTesting())}'";

        return (array) $this->db->query($query)->rows;
    }

    private function isShowLockersMap()
    {
        $sameday_show_lockers_map = $this->getConfig('sameday_show_lockers_map');

        return (null === $sameday_show_lockers_map || $sameday_show_lockers_map === '1');
    }

    /**
     * @return array
     */
    public function showLockersList()
    {
        // If client wants to show lockers map is not need any more the lockers list from local import
        if (!$this->isShowLockersMap()) {
            return [];
        }

        $lockers = array();
        foreach ($this->getLockers() as $locker) {
            if ('' !== $locker['city']) {
                $lockers[$locker['city'] . ' (' . $locker['county'] . ')'][] = $locker;
            }
        }

        return $lockers;
    }

    /**
     * @param $lockerId
     * @param $testing
     * @return mixed
     */
    private function getLockerSameday($lockerId, $testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_locker WHERE locker_id='{$this->db->escape($lockerId)}' AND testing='{$this->db->escape($testing)}'";

        return $this->db->query($query)->row;
    }

    /**
     * @param object $lockerObject
     * @param int $testing
     */
    private function addLocker($lockerObject, $testing)
    {
        $query = '
            INSERT INTO ' . DB_PREFIX . "sameday_locker (
                locker_id,
                name,
                county,
                city,
                address,
                lat,
                lng,
                postal_code,
                boxes,
                testing
            ) VALUES (
                '{$this->db->escape($lockerObject->getId())}',
                '{$this->db->escape($lockerObject->getName())}',
                '{$this->db->escape($lockerObject->getCounty())}',
                '{$this->db->escape($lockerObject->getCity())}',
                '{$this->db->escape($lockerObject->getAddress())}',
                '{$this->db->escape($lockerObject->getLat())}',
                '{$this->db->escape($lockerObject->getLong())}',
                '{$this->db->escape($lockerObject->getPostalCode())}',
                '{$this->db->escape(serialize($lockerObject->getBoxes()))}',
                '{$this->db->escape($testing)}')";

        $this->db->query($query);
    }

    private function updateLocker($lockerObject, $lockerId)
    {
        $this->db->query(
            'UPDATE ' . DB_PREFIX . "sameday_locker SET 
                name='{$this->db->escape($lockerObject->getName())}',
                city='{$this->db->escape($lockerObject->getCity())}', 
                county='{$this->db->escape($lockerObject->getCounty())}',
                address='{$this->db->escape($lockerObject->getAddress())}',
                lat='{$this->db->escape($lockerObject->getLat())}',
                lng='{$this->db->escape($lockerObject->getLong())}',
                postal_code='{$this->db->escape($lockerObject->getPostalCode())}',
                boxes='{$this->db->escape(serialize($lockerObject->getBoxes()))}'
            WHERE 
                id='{$lockerId}'
        ");
    }

    private function deleteLocker($id)
    {
        $query = 'DELETE FROM ' . DB_PREFIX . "sameday_locker WHERE id='{$this->db->escape($id)}'";

        $this->db->query($query);
    }

    /**
     * @return mixed
     */
    private function isTesting()
    {
        return $this->getConfig('sameday_testing');
    }

    /**
     * @return mixed
     */
    public function getHostCountry()
    {
        return $this->getConfig('sameday_host_country');
    }

    public function getApiUsername()
    {
        return $this->getConfig('sameday_username');
    }

    /**
     * @param $address
     * @param $serviceId
     * @return float|null
     * @throws \Sameday\Exceptions\SamedayAuthenticationException
     * @throws \Sameday\Exceptions\SamedayAuthorizationException
     * @throws \Sameday\Exceptions\SamedayNotFoundException
     * @throws \Sameday\Exceptions\SamedayOtherException
     * @throws \Sameday\Exceptions\SamedaySDKException
     * @throws \Sameday\Exceptions\SamedayServerException
     */
    private function estimateCost($address, $serviceId)
    {
        $pickupPointId = $this->getDefaultPickupPointId($this->getConfig('sameday_testing'));
        $weight = $this->cart->getWeight();

        $selectedPaymentMethod = $this->request->cookie['selected_payment_method'] ?? null;
        $repayment = 0;
        if (null === $selectedPaymentMethod || $this->samedayHelper::CASH_ON_DELIVERY_CODE === $selectedPaymentMethod) {
            $repayment = $this->cart->getTotal();
        }

        $estimateCostRequest = new Sameday\Requests\SamedayPostAwbEstimationRequest(
            $pickupPointId,
            null,
            new Sameday\Objects\Types\PackageType(
                \Sameday\Objects\Types\PackageType::PARCEL
            ),
            [new \Sameday\Objects\ParcelDimensionsObject($weight)],
            $serviceId,
            new Sameday\Objects\Types\AwbPaymentType(
                \Sameday\Objects\Types\AwbPaymentType::CLIENT
            ),
            new Sameday\Objects\PostAwb\Request\AwbRecipientEntityObject(
                ucwords(strtolower($address['city'])) !== 'Bucuresti' ? $address['city'] : 'Sector 1',
                $address['zone'],
                ltrim($address['address_1']) . " " . $address['address_2'],
                null,
                null,
                null,
                null,
                $address['postcode']
            ),
            0,
            $repayment,
            null,
            array()
        );

        $sameday =  new Sameday\Sameday($this->samedayHelper->initClient());

        try {
            return $sameday->postAwbEstimation($estimateCostRequest)->getCost();
        } catch (\Sameday\Exceptions\SamedayBadRequestException $exception) {
            return null;
        }
    }

    /**
     * @param bool $testing
     *
     * @return array
     */
    private function getAvailableServices($testing)
    {
        $query = 'SELECT * FROM ' . DB_PREFIX . "sameday_service WHERE testing='{$this->db->escape($testing)}' AND status>0";
        $services = $this->db->query($query)->rows;

        $availableServices = array();
        foreach ($services as $service) {
            if ($service['status'] == 1) {
                $availableServices[] = $service;
            }
        }

        return $availableServices;
    }

    /**
     * @param $testing
     *
     * @return  int|null
     */
    private function getDefaultPickupPointId($testing)
    {
        $query = 'SELECT sameday_id FROM ' . DB_PREFIX . "sameday_pickup_point WHERE testing='{$this->db->escape($testing)}' AND default_pickup_point=1";
        $defaultPickupPoint = $this->db->query($query)->row;

        if (empty($defaultPickupPoint)) {
            return null;
        }

        return $defaultPickupPoint['sameday_id'];
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    private function getConfig($key)
    {
        return $this->config->get("{$this->getPrefix()}$key");
    }

    /**
     * @return string
     */
    private function getPrefix()
    {
        if (strpos(VERSION, '2') === 0) {
            return '';
        }

        return 'shipping_';
    }

    /**
     * @param $code
     * @param $data
     * @param int $store_id
     */
    public function addAdditionalSetting($code, $data, $store_id = 0) {
        foreach ($data as $key => $value) {
            if (substr($key, 0, strlen($code)) == $code) {
                $this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE store_id = '" . (int)$store_id . "' AND `code` = '" . $this->db->escape($code) . "' AND `key` = '".$this->db->escape($key)."'");
                if (!is_array($value)) {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape($value) . "'");
                } else {
                    $this->db->query("INSERT INTO " . DB_PREFIX . "setting SET store_id = '" . (int)$store_id . "', `code` = '" . $this->db->escape($code) . "', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape(json_encode($value, true)) . "', serialized = '1'");
                }
            }
        }
    }
}
