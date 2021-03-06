<?php

namespace BCLib\Alma;

class HoldingsService
{
    /**
     * @var AlmaSoapClient
     */
    protected $_soap;

    /**
     * @var Holding
     */
    protected $_holding_prototype;

    /**
     * @var AlmaCache
     */
    protected $_cache;

    protected $_cache_ttl;

    public function __construct(AlmaSoapClient $soap_client, Holding $holding_prototype, AlmaCache $cache)
    {
        $this->_soap = $soap_client;
        $this->_holding_prototype = $holding_prototype;
        $this->_cache = $cache;
        $this->_cache_ttl = 60;
    }

    /**
     * @param array $mms_ids
     *
     * @return SoapBibRecord[]
     * @throws \File_MARC_Exception
     */
    public function getHoldings(array $mms_ids)
    {
        $records = [];
        $mms_to_send = [];

        for ($i = 0, $max = count($mms_ids); $i < $max; $i++) {
            $key = $this->_cache->key(SoapBibRecord::class, $mms_ids[$i]);
            if ($this->_cache->contains($key)) {
                $records[] = $this->_cache->read($key);
            } else {
                $mms_to_send[] = $mms_ids[$i];
            }
        }

        $params = ['arg0' => \implode(',', $mms_to_send)];

        $results = $this->_soap->execute('retrieveHoldingsInformation', $params);

        foreach ($results->{'OAI-PMH'} as $result) {
            $xml = $result->ListRecords->record->metadata->record->asXML();
            $marcxml = new \File_MARCXML($xml, \File_MARCXML::SOURCE_STRING);
            $bib_record = new SoapBibRecord($marcxml->next(), $this->_holding_prototype);
            $this->_cache->save($bib_record->mms, $bib_record, $this->_cache_ttl);
            $records[] = $bib_record;
        }

        return $records;
    }
}