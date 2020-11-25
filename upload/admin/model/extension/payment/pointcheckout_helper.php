<?php

class ModelExtensionPaymentPointCheckoutHelper extends Model {

    public function getCountries($data = array()) {
        if ($data) {
            $sql = "SELECT * FROM " . DB_PREFIX . "country";
            if(isset($data['filter_name'])){
                $sql.= " where name LIKE '%" . $data['filter_name'] . "%' ";
            }
            $sort_data = array(
                'name',
                'iso_code_2',
                'iso_code_3'
            );
            
            if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
                $sql .= " ORDER BY " . $data['sort'];
            } else {
                $sql .= " ORDER BY name";
            }
            
            if (isset($data['order']) && ($data['order'] == 'DESC')) {
                $sql .= " DESC";
            } else {
                $sql .= " ASC";
            }
            
            if (isset($data['start']) || isset($data['limit'])) {
                if ($data['start'] < 0) {
                    $data['start'] = 0;
                }
                
                if ($data['limit'] < 1) {
                    $data['limit'] = 20;
                }
                
                $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
            }
            
            $query = $this->db->query($sql);
            
            return $query->rows;
        } else {
            $country_data = $this->cache->get('country.admin');
            
            if (!$country_data) {
                $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "country ORDER BY name ASC");
                
                $country_data = $query->rows;
                
                $this->cache->set('country.admin', $country_data);
            }
            
            return $country_data;
        }
    }
    
    
    public function getUserGroups($data = array()) {
        $sql = "SELECT * FROM " . DB_PREFIX . "customer_group cg LEFT JOIN " . DB_PREFIX ."customer_group_description cgd ON (cg.customer_group_id = cgd.customer_group_id) WHERE cgd.language_id = '" . (int)$this->config->get('config_language_id') . "'";
        
        if(isset($data['filter_name'])){
            $sql.= " AND cgd.name LIKE '%" . $data['filter_name'] . "%' ";
        }
        $sort_data = array(
            'name',
            'sort_order'
        );
        
        if (isset($data['sort']) && in_array($data['sort'], $sort_data)) {
            $sql .= " ORDER BY " . $data['sort'];
        } else {
            $sql .= " ORDER BY cgd.name";
        }
        
        if (isset($data['order']) && ($data['order'] == 'DESC')) {
            $sql .= " DESC";
        } else {
            $sql .= " ASC";
        }
        
        if (isset($data['start']) || isset($data['limit'])) {
            if ($data['start'] < 0) {
                $data['start'] = 0;
            }
            
            if ($data['limit'] < 1) {
                $data['limit'] = 20;
            }
            
            $sql .= " LIMIT " . (int)$data['start'] . "," . (int)$data['limit'];
        }
        
        $query = $this->db->query($sql);
        
        return $query->rows;
    }
    
}