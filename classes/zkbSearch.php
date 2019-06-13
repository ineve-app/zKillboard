<?php

class zkbSearch
{
    public static $imageMap = ['typeID' => 'Type/%1$d_32.png', 'groupID' => 'Type/1_32.png', 'characterID' => 'Character/%1$d_32.jpg', 'corporationID' => 'Corporation/%1$d_32.png', 'allianceID' => 'Alliance/%1$d_32.png', 'factionID' => 'Alliance/%1$d_32.png', 'solarSystemID' => 'Type/3802_32.png', 'regionID' => 'Type/3_32.png', 'locationID' => 'Type/1_32.png'];

    public static function getResults($search, $entityType = null)
    {
        global $redis, $mdb;

        $search = strtolower($search);
        $regex = (substr($search, 0, 2) == "r:" && strlen($search) > 2) ? substr($search, 2) : null;
        $search = (substr($search, 0, 2) == "r:" && strlen($search) > 2) ? substr($search, 2) : $search;
        //$low = "[$search\x00";
        $low = $search;

        $exactMatch = [];
        $partialMatch = [];
        $types = ['typeID:flag', 'regionID', 'solarSystemID', 'allianceID', 'allianceID:flag', 'corporationID', 'corporationID:flag', 'characterID', 'typeID', 'groupID', 'locationID'];
        foreach ($types as $type) {
            if ($entityType != null && $entityType != $type) {
                continue;
            }

            $sub = $low;
            do {
                $result = unserialize($redis->hget("search:$type", $sub));
                if ($result == null) $result = [];
                if (sizeof($result) == 0) $sub = substr($sub, 0, strlen($sub) - 1);
            } while (sizeof($result) == 0 && strlen($sub) > 0);

            $searchType = $type;
            $type = str_replace(':flag', '', $type);
            foreach ($result as $row) {
                $split = explode("\x00", $row);
                if (sizeof($split) == 2 && substr($split[0], 0, strlen($search)) != $search) {
                    continue;
                }
                $id = $split[1];
                $info = Info::getInfo($type, $id);
                $name = $info['name'];
                $image = isset(self::$imageMap[$type]) ? self::$imageMap[$type] : '';
                $image = sprintf($image, $id);
                if ($searchType == 'typeID:flag') {
                    $searchType = 'ship';
                }
                if ($searchType == 'allianceID:flag') {
                    $searchType = 'alliance';
                }
                if ($searchType == 'corporationID:flag') {
                    $searchType = 'corporation';
                }
                if ($searchType == 'typeID') {
                    $searchType = 'item';
                }
                if ($searchType == 'groupID') {
                    $searchType = 'group';
                }
                if ($searchType == 'solarSystemID') {
                    $searchType = 'system';
                }
                if ($searchType == 'alliance' || $searchType == 'allianceID' || $searchType == 'corporation' || $searchType == 'corporationID') {
                    if (@$info['memberCount'] == 0) $name = "$name (Closed)";
                }
                if ($searchType == 'character' || $searchType == 'characterID') {
                    if (@$info['corporationID'] == 1000001) $name = "$name (recycled)";
                }
                if ($searchType == 'system') {
                    $regionID = Info::getInfoField('solarSystemID', $id, 'regionID');
                    $regionName = Info::getInfoField('regionID', $regionID, 'name');
                    $name = "$name ($regionName)";
                }
                if (strtolower($name) === $search) {
                    $exactMatch[] = ['id' => (int) $id, 'name' => $name, 'type' => str_replace('ID', '', $searchType), 'image' => $image];
                } else {
                    $partialMatch[] = ['id' => (int) $id, 'name' => $name, 'type' => str_replace('ID', '', $searchType), 'image' => $image];
                }
            }
        }

        $result = array_merge($exactMatch, $partialMatch);
        if (sizeof($result) > 15) {
            $result = array_slice($result, 0, 15);
        }

        return $result;
    }
}
