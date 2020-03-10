<?php

/******************************************************************************/
/******************************************************************************/

class CHBSVehicle
{
	/**************************************************************************/
	
    function __construct()
    {
        
    }
        
    /**************************************************************************/
    
    public function init()
    {
        $this->registerCPT();
    }
    
	/**************************************************************************/

    public static function getCPTName()
    {
        return(PLUGIN_CHBS_CONTEXT.'_vehicle');
    }
    
    /**************************************************************************/
    
    public static function getCPTCategoryName()
    {
        return(self::getCPTName().'_c');
    }
    
    /**************************************************************************/
    
    private function registerCPT()
    {
		register_post_type
		(
			self::getCPTName(),
			array
			(
				'labels'														=>	array
				(
					'name'														=>	__('Vehicles','chauffeur-booking-system'),
					'singular_name'												=>	__('Vehicle','chauffeur-booking-system'),
					'add_new'													=>	__('Add New','chauffeur-booking-system'),
					'add_new_item'												=>	__('Add New Vehicle','chauffeur-booking-system'),
					'edit_item'													=>	__('Edit Vehicle','chauffeur-booking-system'),
					'new_item'													=>	__('New Vehicle','chauffeur-booking-system'),
					'all_items'													=>	__('Vehicles','chauffeur-booking-system'),
					'view_item'													=>	__('View Vehicle','chauffeur-booking-system'),
					'search_items'												=>	__('Search Vehicles','chauffeur-booking-system'),
					'not_found'													=>	__('No Vehicles Found','chauffeur-booking-system'),
					'not_found_in_trash'										=>	__('No Vehicles Found in Trash','chauffeur-booking-system'), 
					'parent_item_colon'											=>	'',
					'menu_name'													=>	__('Vehicles','chauffeur-booking-system')
				),	
				'public'														=>	(PLUGIN_CHBS_VEHICLE_TEMPLATE ? true : false),  
				'show_ui'														=>	true, 
				'show_in_menu'													=>	'edit.php?post_type='.CHBSBooking::getCPTName(),
				'capability_type'												=>	'post',
				'menu_position'													=>	2,
				'hierarchical'													=>	false,  
				'rewrite'														=>	false,  
				'supports'														=>	array('title','editor','thumbnail','page-attributes')
			)
		);
        
		register_taxonomy
		(
			self::getCPTCategoryName(),
			self::getCPTName(),
			array
			(
				'label'                                                         =>	__('Vehicle Types','chauffeur-booking-system'),
                'hierarchical'                                                  =>  false
			)
		);
        
        add_action('save_post',array($this,'savePost'));
        add_action('add_meta_boxes_'.self::getCPTName(),array($this,'addMetaBox'));
        add_filter('postbox_classes_'.self::getCPTName().'_chbs_meta_box_vehicle',array($this,'adminCreateMetaBoxClass'));
        
		add_filter('manage_edit-'.self::getCPTName().'_columns',array($this,'manageEditColumns')); 
		add_action('manage_'.self::getCPTName().'_posts_custom_column',array($this,'managePostsCustomColumn'));
		add_filter('manage_edit-'.self::getCPTName().'_sortable_columns',array($this,'manageEditSortableColumns'));
        
        add_action('restrict_manage_posts',array($this,'restrictManagePosts'));
        add_filter('parse_query',array($this,'parseQuery'));
    }

    /**************************************************************************/
    
    function addMetaBox()
    {
        add_meta_box(PLUGIN_CHBS_CONTEXT.'_meta_box_vehicle',__('Main','chauffeur-booking-system'),array($this,'addMetaBoxMain'),self::getCPTName(),'normal','low');		
    }
    
    /**************************************************************************/
    
    function addMetaBoxMain()
    {
        global $post;
        
		$data=array();
        
        $Date=new CHBSDate();
        $TaxRate=new CHBSTaxRate();
        $PriceType=new CHBSPriceType();
        $VehicleCompany=new CHBSVehicleCompany();
        $VehicleAttribute=new CHBSVehicleAttribute();
        
        $data['meta']=CHBSPostMeta::getPostMeta($post);
        
		$data['nonce']=CHBSHelper::createNonceField(PLUGIN_CHBS_CONTEXT.'_meta_box_vehicle');
       
        $data['dictionary']['day']=$Date->day;
        $data['dictionary']['tax_rate']=$TaxRate->getDictionary();
        $data['dictionary']['price_type']=$PriceType->getPriceType();
        $data['dictionary']['vehicle_company']=$VehicleCompany->getDictionary();
        $data['dictionary']['vehicle_attribute']=$VehicleAttribute->getDictionary();
        
		$Template=new CHBSTemplate($data,PLUGIN_CHBS_TEMPLATE_PATH.'admin/meta_box_vehicle.php');
		echo $Template->output();	        
    }
    
    /**************************************************************************/
    
    function adminCreateMetaBoxClass($class) 
    {
        array_push($class,'to-postbox-1');
        return($class);
    }
    
    /**************************************************************************/
    
    function savePost($postId)
    {      
        if(!$_POST) return(false);
        
        if(CHBSHelper::checkSavePost($postId,PLUGIN_CHBS_CONTEXT.'_meta_box_vehicle_noncename','savePost')===false) return(false);
        
        $Date=new CHBSDate();
        $TaxRate=new CHBSTaxRate();
        $PriceType=new CHBSPriceType();
        $Validation=new CHBSValidation();
        $VehicleCompany=new CHBSVehicleCompany();
        
        $option=CHBSHelper::getPostOption();
        
        if(!$Validation->isNumber($option['passenger_count'],1,99)) 
            $option['passenger_count']=4;
        if(!$Validation->isNumber($option['bag_count'],1,99)) 
            $option['bag_count']=4;      
        if(!$Validation->isNumber($option['standard'],1,4)) 
            $option['standard']=1;    
        
        /***/
        
        $dictionary=$VehicleCompany->getDictionary();
        if(!$VehicleCompany->isVehicleCompany($option['vehicle_company_id'],$dictionary))
            $option['vehicle_company_id']=0;
        
        /***/
        
        $option['gallery_image_id']=array_map('intval',preg_split('/\./',$option['gallery_image_id']));
        foreach($option['gallery_image_id'] as $index=>$value)
        {
            if($value<=0) unset($option['gallery_image_id'][$index]);
        }
        
        if($Validation->isEmpty($option['base_location']))
        {
            $option['base_location_coordinate_lat']='';
            $option['base_location_coordinate_lng']='';
        }

        /***/
        
        if(!$PriceType->isPriceType($option['price_type']))
            $option['price_type']=1;

        if(!$Validation->isPrice($option['price_fixed_value'],false))
           $option['price_fixed_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_fixed_tax_rate_id']))
            $option['price_fixed_tax_rate_id']=0;

        if(!$Validation->isPrice($option['price_fixed_return_value'],false))
           $option['price_fixed_return_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_fixed_return_tax_rate_id']))
            $option['price_fixed_return_tax_rate_id']=0;
        
        if(!$Validation->isPrice($option['price_fixed_return_new_ride_value'],false))
           $option['price_fixed_return_new_ride_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_fixed_return_new_ride_tax_rate_id']))
            $option['price_fixed_return_new_ride_tax_rate_id']=0;        
        
        if(!$Validation->isPrice($option['price_initial_value'],false))
           $option['price_initial_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_initial_tax_rate_id']))
            $option['price_initial_tax_rate_id']=0;

        if(!$Validation->isPrice($option['price_delivery_value'],false))
           $option['price_delivery_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_delivery_tax_rate_id']))
            $option['price_delivery_tax_rate_id']=0;        
        
        if(!$Validation->isPrice($option['price_delivery_return_value'],false))
           $option['price_delivery_return_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_delivery_return_tax_rate_id']))
            $option['price_delivery_return_tax_rate_id']=0;   
        
        if(!$Validation->isPrice($option['price_distance_value'],false))
           $option['price_distance_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_distance_tax_rate_id']))
            $option['price_distance_tax_rate_id']=0;

        if(!$Validation->isPrice($option['price_distance_return_value'],false))
           $option['price_distance_return_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_distance_return_tax_rate_id']))
            $option['price_distance_return_tax_rate_id']=0;

        if(!$Validation->isPrice($option['price_distance_return_new_ride_value'],false))
           $option['price_distance_return_new_ride_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_distance_return_new_ride_tax_rate_id']))
            $option['price_distance_return_new_ride_tax_rate_id']=0;
        
        if(!$Validation->isPrice($option['price_hour_value'],false))
           $option['price_hour_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_hour_tax_rate_id']))
            $option['price_hour_tax_rate_id']=0;
        
        if(!$Validation->isPrice($option['price_hour_return_value'],false))
           $option['price_hour_return_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_hour_return_tax_rate_id']))
            $option['price_hour_return_tax_rate_id']=0;

        if(!$Validation->isPrice($option['price_hour_return_new_ride_value'],false))
           $option['price_hour_return_new_ride_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_hour_return_new_ride_tax_rate_id']))
            $option['price_hour_return_new_ride_tax_rate_id']=0;              

        if(!$Validation->isPrice($option['price_extra_time_value'],false))
           $option['price_extra_time_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_extra_time_tax_rate_id']))
            $option['price_extra_time_tax_rate_id']=0; 
        
        if(!$Validation->isPrice($option['price_passenger_adult_value'],false))
           $option['price_passenger_adult_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_passenger_adult_tax_rate_id']))
            $option['price_passenger_adult_tax_rate_id']=0; 
        
        if(!$Validation->isPrice($option['price_passenger_children_value'],false))
           $option['price_passenger_children_value']=0.00;
        if(!$TaxRate->isTaxRate($option['price_passenger_children_tax_rate_id']))
            $option['price_passenger_children_tax_rate_id']=0;
        /***/
        
        $attribute=array();
        
        $attributePost=$option['attribute'];
        
        $VehicleAttribute=new CHBSVehicleAttribute();
        $attributeDictionary=$VehicleAttribute->getDictionary();

        foreach($attributeDictionary as $attributeDictionaryIndex=>$attributeDictionaryValue)
        {
            if(!isset($attributePost[$attributeDictionaryIndex])) continue;
            
            switch($attributeDictionaryValue['meta']['attribute_type'])
            {
                case 1:
                    
                    $attribute[$attributeDictionaryIndex]=$attributePost[$attributeDictionaryIndex];
                    
                break;
                
                case 2:
                case 3:
                    
                    if(!is_array($attributePost[$attributeDictionaryIndex])) break;
                    
                    foreach($attributeDictionaryValue['meta']['attribute_value'] as $value)
                    {
                        if(in_array($value['id'],$attributePost[$attributeDictionaryIndex]))
                        {
                            if($attributeDictionaryValue['meta']['attribute_type']===2)
                            {
                                $attribute[$attributeDictionaryIndex]=(int)$value['id'];
                                break;
                            }
                            else $attribute[$attributeDictionaryIndex][]=(int)$value['id'];
                        }
                    }
    
                break;
            }
        }
        
        /***/
        
		$dateExclude=array();
        $dateExcludePost=CHBSHelper::getPostValue('date_exclude');
        
        $count=count($dateExcludePost);
        
        for($i=0;$i<$count;$i+=4)
		{
            $dateExcludePost[$i]=$Date->formatDateToStandard($dateExcludePost[$i]);
            $dateExcludePost[$i+1]=$Date->formatTimeToStandard($dateExcludePost[$i+1]);
            $dateExcludePost[$i+2]=$Date->formatDateToStandard($dateExcludePost[$i+2]);
            $dateExcludePost[$i+3]=$Date->formatTimeToStandard($dateExcludePost[$i+3]);
            
            if($Validation->isEmpty($dateExcludePost[$i+1])) $dateExcludePost[$i+1]='00:00';
            if($Validation->isEmpty($dateExcludePost[$i+3])) $dateExcludePost[$i+3]='00:00';
            
			if(!$Validation->isDate($dateExcludePost[$i],true)) continue;
			if(!$Validation->isDate($dateExcludePost[$i+2],true)) continue;

			if(!$Validation->isTime($dateExcludePost[$i+1],true)) continue;
			if(!$Validation->isTime($dateExcludePost[$i+3],true)) continue;
            
			if($Date->compareDate($dateExcludePost[$i],$dateExcludePost[$i+2])==1) continue;
            if($Date->compareDate(date_i18n('d-m-Y'),$dateExcludePost[$i])==1) continue;
            
            if($Date->compareDate($dateExcludePost[$i],$dateExcludePost[$i+2])===0)
            {
                if($Date->compareTime($dateExcludePost[$i+1],$dateExcludePost[$i+3])===1) continue;
            }
            
			$dateExclude[]=array('startDate'=>$dateExcludePost[$i],'startTime'=>$dateExcludePost[$i+1],'stopDate'=>$dateExcludePost[$i+2],'stopTime'=>$dateExcludePost[$i+3]);
		}
        
        /***/
        
        $vehicleAvailabilityDayNumber=CHBSHelper::getPostValue('vehicle_availability_day_number');
        
        if(in_array(-1,$vehicleAvailabilityDayNumber))
        {
            $option['vehicle_availability_day_number']=array(-1);
        }
        else
        {
            foreach($vehicleAvailabilityDayNumber as $index=>$value)
            {
                if(!array_key_exists($value,$Date->day))
                    unset($vehicleAvailabilityDayNumber[$index]);                
            }
        }
        
        if(!count($vehicleAvailabilityDayNumber))
            $vehicleAvailabilityDayNumber=array(-1);
        
        /***/
        
        $key=array
        (
            'vehicle_make',
            'vehicle_model',
            'vehicle_company_id',
            'passenger_count',
            'bag_count',
            'standard',
            'base_location',
            'base_location_coordinate_lat',
            'base_location_coordinate_lng',
            'gallery_image_id',
            'price_type',
            'price_fixed_value',
            'price_fixed_tax_rate_id',
            'price_fixed_return_value',
            'price_fixed_return_tax_rate_id',
            'price_fixed_return_new_ride_value',
            'price_fixed_return_new_ride_tax_rate_id',
            'price_initial_value',
            'price_initial_tax_rate_id',
            'price_delivery_value',
            'price_delivery_tax_rate_id',
            'price_delivery_return_value',
            'price_delivery_return_tax_rate_id',
            'price_distance_value',
            'price_distance_tax_rate_id',
            'price_distance_return_value',
            'price_distance_return_tax_rate_id',
            'price_distance_return_new_ride_value',
            'price_distance_return_new_ride_tax_rate_id',
            'price_hour_value',
            'price_hour_tax_rate_id',
            'price_hour_return_value',
            'price_hour_return_tax_rate_id',            
            'price_hour_return_new_ride_value',
            'price_hour_return_new_ride_tax_rate_id',              
            'price_extra_time_value',
            'price_extra_time_tax_rate_id',
            'price_passenger_adult_value',
            'price_passenger_adult_tax_rate_id',
            'price_passenger_children_value',
            'price_passenger_children_tax_rate_id'
        );

		foreach($key as $value)
			CHBSPostMeta::updatePostMeta($postId,$value,$option[$value]);
        
        CHBSPostMeta::updatePostMeta($postId,'attribute',$attribute);
        CHBSPostMeta::updatePostMeta($postId,'date_exclude',$dateExclude);
        CHBSPostMeta::updatePostMeta($postId,'vehicle_availability_day_number',$vehicleAvailabilityDayNumber);
    }
    
	/**************************************************************************/
	
	function setPostMetaDefault(&$meta)
	{
        $TaxRate=new CHBSTaxRate();
        $VehicleAttribute=new CHBSVehicleAttribute();
        
		CHBSHelper::setDefault($meta,'vehicle_make','');
        CHBSHelper::setDefault($meta,'vehicle_model','');
        CHBSHelper::setDefault($meta,'vehicle_company_id',0);
        
        CHBSHelper::setDefault($meta,'passenger_count','4');
        CHBSHelper::setDefault($meta,'bag_count','4');
        CHBSHelper::setDefault($meta,'standard','1');
        
        CHBSHelper::setDefault($meta,'base_location','');
        CHBSHelper::setDefault($meta,'base_location_coordinate_lat','');
        CHBSHelper::setDefault($meta,'base_location_coordinate_lng','');
        
        CHBSHelper::setDefault($meta,'gallery_image_id',array());
        
        CHBSHelper::setDefault($meta,'price_type',1);
        
        CHBSHelper::setDefault($meta,'price_fixed_value','0.00');
        CHBSHelper::setDefault($meta,'price_fixed_tax_rate_id',$TaxRate->getDefaultTaxPostId());

        CHBSHelper::setDefault($meta,'price_fixed_return_value','0.00');
        CHBSHelper::setDefault($meta,'price_fixed_return_tax_rate_id',$TaxRate->getDefaultTaxPostId());
        
        CHBSHelper::setDefault($meta,'price_fixed_return_new_ride_value','0.00');
        CHBSHelper::setDefault($meta,'price_fixed_return_new_ride_tax_rate_id',$TaxRate->getDefaultTaxPostId());
        
        CHBSHelper::setDefault($meta,'price_initial_value','0.00');
        CHBSHelper::setDefault($meta,'price_initial_tax_rate_id',$TaxRate->getDefaultTaxPostId());

        CHBSHelper::setDefault($meta,'price_delivery_value','0.00');
        CHBSHelper::setDefault($meta,'price_delivery_tax_rate_id',$TaxRate->getDefaultTaxPostId());        
        
        CHBSHelper::setDefault($meta,'price_delivery_return_value','0.00');
        CHBSHelper::setDefault($meta,'price_delivery_return_tax_rate_id',$TaxRate->getDefaultTaxPostId());  
        
        CHBSHelper::setDefault($meta,'price_distance_value','0.00');
        CHBSHelper::setDefault($meta,'price_distance_tax_rate_id',$TaxRate->getDefaultTaxPostId());

        CHBSHelper::setDefault($meta,'price_distance_return_value','0.00');
        CHBSHelper::setDefault($meta,'price_distance_return_tax_rate_id',$TaxRate->getDefaultTaxPostId());

        CHBSHelper::setDefault($meta,'price_distance_return_new_ride_value','0.00');
        CHBSHelper::setDefault($meta,'price_distance_return_new_ride_tax_rate_id',$TaxRate->getDefaultTaxPostId());
        
        CHBSHelper::setDefault($meta,'price_hour_value','0.00');
        CHBSHelper::setDefault($meta,'price_hour_tax_rate_id',$TaxRate->getDefaultTaxPostId());

        CHBSHelper::setDefault($meta,'price_hour_return_value','0.00');
        CHBSHelper::setDefault($meta,'price_hour_return_tax_rate_id',$TaxRate->getDefaultTaxPostId());
        
        CHBSHelper::setDefault($meta,'price_hour_return_new_ride_value','0.00');
        CHBSHelper::setDefault($meta,'price_hour_return_new_ride_tax_rate_id',$TaxRate->getDefaultTaxPostId());        
        
        CHBSHelper::setDefault($meta,'price_extra_time_value','0.00');
        CHBSHelper::setDefault($meta,'price_extra_time_tax_rate_id',$TaxRate->getDefaultTaxPostId());  

        CHBSHelper::setDefault($meta,'price_passenger_adult_value','0.00');
        CHBSHelper::setDefault($meta,'price_passenger_adult_tax_rate_id',$TaxRate->getDefaultTaxPostId());  

        CHBSHelper::setDefault($meta,'price_passenger_children_value','0.00');
        CHBSHelper::setDefault($meta,'price_passenger_children_tax_rate_id',$TaxRate->getDefaultTaxPostId());  
        
        $attribute=$VehicleAttribute->getDictionary();
        foreach($attribute as $attributeIndex=>$attributeData)
        {
            if(isset($meta['attribute'][$attributeIndex])) continue;
            
            if($attributeData['meta']['attribute_type']==1)
                $meta['attribute'][$attributeIndex]='';
            else $meta['attribute'][$attributeIndex]=array(-1);
        }
        
		if(!array_key_exists('date_exclude',$meta))
			$meta['date_exclude']=array();
        
        CHBSHelper::setDefault($meta,'vehicle_availability_day_number',array(-1));
	}
    
    /**************************************************************************/
    
    function getDictionary($attr=array(),$sortingType=1)
    {
		global $post;
		
		$dictionary=array();
		
		$default=array
		(
			'vehicle_id'           												=>	0,
            'category_id'                                                       =>  0
		);
		
		$attribute=shortcode_atts($default,$attr);
        
        $Validation=new CHBSValidation();
		
		CHBSHelper::preservePost($post,$bPost);
		
		$argument=array
		(
			'post_type'															=>	self::getCPTName(),
			'post_status'														=>	'publish',
			'posts_per_page'													=>	-1,
			'orderby'                                                           =>  'menu_order',
            'order'                                                             =>  ((int)$sortingType===4 ? 'desc' : 'asc')
		);

		if($attribute['vehicle_id'])
			$argument['p']=$attribute['vehicle_id'];
 
        if(!is_array($attribute['category_id']))
            $attribute['category_id']=array($attribute['category_id']);

        if(array_sum($attribute['category_id']))
        {
            $argument['tax_query']=array
            (
                array
                (
                    'taxonomy'                                                  =>  self::getCPTCategoryName(),
                    'field'                                                     =>  'term_id',
                    'terms'                                                     =>  $attribute['category_id'],
                    'operator'                                                  =>  'IN'
                )
            );
        }
         
        $query=new WP_Query($argument);
		if($query===false) return($dictionary);
 
		while($query->have_posts())
		{
			$query->the_post();
			$dictionary[$post->ID]['post']=$post;
			$dictionary[$post->ID]['meta']=CHBSPostMeta::getPostMeta($post);
            
            if($Validation->isEmpty($post->post_title))
                $post->post_title=trim($dictionary[$post->ID]['meta']['vehicle_make'].' '.$dictionary[$post->ID]['meta']['vehicle_model']);
		}
        
		CHBSHelper::preservePost($post,$bPost,0);	
        
		return($dictionary);        
    }
    
    /**************************************************************************/
    
    function getCategory()
    {
        $category=array();
        
        $result=get_terms(self::getCPTCategoryName());
        if(is_wp_error($result)) return($category);
        
        foreach($result as $value)
            $category[$value->{'term_id'}]=array('name'=>$value->{'name'});
        
        return($category);
    }
    
    /**************************************************************************/
    
    function getMake()
    {
        $Validation=new CHBSValidation();
        
        $vehicle=$this->getDictionary();
        
        $make=array();
   
        foreach($vehicle as $value)
        {
            if($Validation->isEmpty($value['meta']['vehicle_make'])) continue;
            
            if(in_array($value['meta']['vehicle_make'],$make)) continue;
            
            array_push($make,$value['meta']['vehicle_make']);
        }
        
        sort($make,SORT_LOCALE_STRING);
                        
        return($make);
    }
    
    /**************************************************************************/
    
    function calculatePrice($data,$calculateHiddenFee=true)
    {
        $Length=new CHBSLength();
        $TaxRate=new CHBSTaxRate();
        $Currency=new CHBSCurrency();
        $PriceRule=new CHBSPriceRule();
        
        $taxRate=$TaxRate->getDictionary();
        
        /***/
        
        $passengerSum=0;
        if(CHBSBookingHelper::isPassengerEnable($data['booking_form']['meta'],$data['service_type_id'],'adult'))
            $passengerSum+=$data['passenger_adult'];
        if(CHBSBookingHelper::isPassengerEnable($data['booking_form']['meta'],$data['service_type_id'],'children'))
            $passengerSum+=$data['passenger_children'];           
        
        /***/
        /***/
        /* init */
        
        $priceBase=array();
       
        /***/
        /* set price from vehicle */
        
        $priceBase=$PriceRule->extractPriceFromData($priceBase,$data['booking_form']['dictionary']['vehicle'][$data['vehicle_id']]['meta']);

        /***/
        
        if((int)$data['service_type_id']==3)
        {
            $dictionary=$data['booking_form']['dictionary']['route'];

            if($dictionary!==false)
            {
                if((array_key_exists($data['route_id'],$dictionary)) && (array_key_exists($data['vehicle_id'],$dictionary[$data['route_id']]['meta']['vehicle'])))
                {
                    $routeVehicle=$dictionary[$data['route_id']]['meta']['vehicle'][$data['vehicle_id']];

                    if((int)$routeVehicle['price_source']===2)
                        $priceBase=$PriceRule->extractPriceFromData($priceBase,$routeVehicle);
                }
            }
        }

        /***/
        /* set price from rule */
        
        $argument=array
        (
            'booking_form_id'                                                   =>  (int)$data['booking_form_id'],
            'service_type_id'                                                   =>  (int)$data['service_type_id'],
            'transfer_type_id'                                                  =>  (int)$data['transfer_type_id'],
            'pickup_location_coordinate'                                        =>  $data['pickup_location_coordinate'],
            'dropoff_location_coordinate'                                       =>  $data['dropoff_location_coordinate'],
            'fixed_location_pickup'                                             =>  (int)$data['fixed_location_pickup'],
            'fixed_location_dropoff'                                            =>  (int)$data['fixed_location_dropoff'],
            'route_id'                                                          =>  (int)$data['route_id'],
            'vehicle_id'                                                        =>  (int)$data['vehicle_id'],
            'pickup_date'                                                       =>  $data['pickup_date'],
            'pickup_time'                                                       =>  $data['pickup_time'],
            'base_location_distance'                                            =>  $data['base_location_distance'],
            'base_location_return_distance'                                     =>  $data['base_location_return_distance'],
            'distance'                                                          =>  $data['distance'],
            'distance_sum'                                                      =>  $data['distance_sum'],
            'duration'                                                          =>  $data['duration'],
            'duration_map'                                                      =>  $data['duration_map'],
            'duration_sum'                                                      =>  $data['duration_sum'],
            'passenger_sum'                                                     =>  $passengerSum
        );
        
        $priceBase=$PriceRule->getPriceFromRule($argument,$data['booking_form'],$priceBase);

        /***/
        
        $currency=$Currency->getCurrency(CHBSCurrency::getFormCurrency());
        
        /***/
        
        $rate=CHBSCurrency::getExchangeRate(); 
        foreach($priceBase as $index=>$value)
        {
            if(preg_match('/\_value$/',$index,$result))
                $priceBase[$index]=$priceBase[$index]*$rate;
        }               
        
        /***/
        
        $distance=$data['distance'];
        if(CHBSOption::getOption('length_unit')==2)
            $distance=$Length->convertUnit($distance);
        
        if((int)$data['service_type_id']==2)
            $duration=$data['duration']/60;
        else $duration=$data['duration_map']/60;
        
        /***/
        
        $Coupon=new CHBSCoupon();
        $coupon=$Coupon->checkCode();
        
        if($coupon!==false)
        {
            $discountPercentage=$coupon['meta']['discount_percentage'];
            foreach($priceBase as $index=>$value)
            {
                if(preg_match('/\_value$/',$index))
                {
                    $priceBase[$index]=round($priceBase[$index]*(1-$discountPercentage/100),2);
                }
            }
        }
        
        /***/
        
        if((int)$priceBase['price_type']===2)
        {
            $priceSumNetValue=$priceBase['price_fixed_value'];
            $priceSumGrossValue=number_format($priceSumNetValue*(1+$TaxRate->getTaxRateValue($priceBase['price_fixed_tax_rate_id'],$taxRate)/100),2,'.',''); 
            
            if(in_array($data['service_type_id'],array(1,3)))
            {
                if(in_array((int)$data['transfer_type_id'],array(2)))
                {
                    $priceSumNetValue+=$priceBase['price_fixed_return_value'];
                    $priceSumGrossValue+=number_format($priceBase['price_fixed_return_value']*(1+$TaxRate->getTaxRateValue($priceBase['price_fixed_return_tax_rate_id'],$taxRate)/100),2,'.','');                     
                }
                elseif(in_array((int)$data['transfer_type_id'],array(3)))
                {
                    $priceSumNetValue+=$priceBase['price_fixed_return_new_ride_value'];
                    $priceSumGrossValue+=number_format($priceBase['price_fixed_return_new_ride_value']*(1+$TaxRate->getTaxRateValue($priceBase['price_fixed_return_new_ride_tax_rate_id'],$taxRate)/100),2,'.','');                     
                }                
            }
        }
        else
        {
            if((CHBSBookingHelper::isPassengerEnable($data['booking_form']['meta'],$data['service_type_id'],'adult')) || (CHBSBookingHelper::isPassengerEnable($data['booking_form']['meta'],$data['service_type_id'],'children'))) 
            {              
                $returnFactor=1;
                if(in_array($data['transfer_type_id'],array(2,3))) $returnFactor=2;
                
                if(CHBSBookingHelper::isPassengerEnable($data['booking_form']['meta'],$data['service_type_id'],'adult'))
                {
                    $priceSumNetValue=$priceBase['price_passenger_adult_value']*$data['passenger_adult']*$returnFactor;
                    $priceSumGrossValue=number_format($priceBase['price_passenger_adult_value']*$data['passenger_adult']*$returnFactor*(1+$TaxRate->getTaxRateValue($priceBase['price_passenger_adult_tax_rate_id'],$taxRate)/100),2,'.','');                     
                }
                
                if(CHBSBookingHelper::isPassengerEnable($data['booking_form']['meta'],$data['service_type_id'],'children'))
                {
                    $priceSumNetValue+=$priceBase['price_passenger_children_value']*$data['passenger_children']*$returnFactor; 
                    $priceSumGrossValue+=number_format($priceBase['price_passenger_children_value']*$data['passenger_children']*$returnFactor*(1+$TaxRate->getTaxRateValue($priceBase['price_passenger_children_tax_rate_id'],$taxRate)/100),2,'.','');                     
                }
            }
            else
            {
                if($distance<3)
                {
                    $priceSumNetValue=$priceBase['price_fixed_value'];
                }
                else
                    if(in_array($data['service_type_id'],array(1,3)))
                    {
                        $priceSumNetValue1=0;
                        $priceSumNetValue2=0;

                        $priceSumNetValue1=$priceBase['price_distance_value']*$distance;                    
                        $priceSumGrossValue=number_format($priceSumNetValue1*(1+$TaxRate->getTaxRateValue($priceBase['price_distance_tax_rate_id'],$taxRate)/100),2,'.','');   

                        if((int)$data['booking_form']['meta']['calculation_method_service_type_'.$data['service_type_id']]===2)
                        {
                            $priceSumNetValue2=$priceBase['price_hour_value']*$duration;
                            $priceSumGrossValue+=number_format($priceSumNetValue2*(1+$TaxRate->getTaxRateValue($priceBase['price_hour_tax_rate_id'],$taxRate)/100),2,'.',''); 
                        }

                        $priceSumNetValue=$priceSumNetValue1+$priceSumNetValue2;

                        if(in_array((int)$data['transfer_type_id'],array(2)))
                        {
                            $priceSumNetValue1=0;
                            $priceSumNetValue2=0;

                            $priceSumNetValue1=$priceBase['price_distance_return_value']*$distance;
                            $priceSumGrossValue+=number_format($priceSumNetValue1*(1+$TaxRate->getTaxRateValue($priceBase['price_distance_return_tax_rate_id'],$taxRate)/100),2,'.',''); 

                            if((int)$data['booking_form']['meta']['calculation_method_service_type_'.$data['service_type_id']]===2)
                            {
                                $priceSumNetValue2=$priceBase['price_hour_return_value']*$duration;
                                $priceSumGrossValue+=number_format($priceSumNetValue2*(1+$TaxRate->getTaxRateValue($priceBase['price_hour_return_tax_rate_id'],$taxRate)/100),2,'.',''); 
                            }

                            $priceSumNetValue+=$priceSumNetValue1+$priceSumNetValue2;                    
                        }
                        elseif(in_array((int)$data['transfer_type_id'],array(3)))
                        {
                            $priceSumNetValue1=0;
                            $priceSumNetValue2=0;

                            $priceSumNetValue1=$priceBase['price_distance_return_new_ride_value']*$distance;
                            $priceSumGrossValue+=number_format($priceSumNetValue1*(1+$TaxRate->getTaxRateValue($priceBase['price_distance_return_new_ride_tax_rate_id'],$taxRate)/100),2,'.','');                      

                            if((int)$data['booking_form']['meta']['calculation_method_service_type_'.$data['service_type_id']]===2)
                            {
                                $priceSumNetValue2=$priceBase['price_hour_return_new_ride_value']*$duration;
                                $priceSumGrossValue+=number_format($priceSumNetValue2*(1+$TaxRate->getTaxRateValue($priceBase['price_hour_return_new_ride_tax_rate_id'],$taxRate)/100),2,'.',''); 
                            }

                            $priceSumNetValue+=$priceSumNetValue1+$priceSumNetValue2;                       
                        }
                    }
                    elseif((int)$data['service_type_id']===2)
                    {
                        $priceSumNetValue=$priceBase['price_hour_value']*$duration;
                        $priceSumGrossValue=number_format($priceSumNetValue*(1+$TaxRate->getTaxRateValue($priceBase['price_hour_tax_rate_id'],$taxRate)/100),2,'.','');                  
                    }
            }
        }
        
        /***/
        
        if(in_array((int)$data['service_type_id'],array(1,3)))
        {
            if(in_array((int)$data['transfer_type_id'],array(2,3)))
            {
                $duration*=2;
                $distance*=2;
            }
        }
        
        /***/
 
        $price=array
        (
            'price'                                                             =>  array
            (
                'base'                                                          =>  $priceBase,
                'sum'                                                           =>  array
                (
                    'net'                                                       =>  array
                    (
                        'value'                                                 =>  number_format($priceSumNetValue,2,'.','')
                    ),
                    'gross'                                                     =>  array
                    (
                        'value'                                                 =>  number_format($priceSumGrossValue,2,'.',''),
                        'format'                                                =>  CHBSPrice::format($priceSumGrossValue,CHBSCurrency::getFormCurrency())
                    )            
                )
            ),
            'currency'                                                          =>  $currency
        );
        
        if((((int)$data['booking_form']['meta']['booking_summary_hide_fee']===1) && ($calculateHiddenFee)) || (($priceBase['minimum_order_value']>0) && ((int)$priceBase['price_type']===1)))
        {
            $Date=new CHBSDate();
            
            $data2=CHBSHelper::getPostOption();
            $data2['booking_form']=$data['booking_form'];
            
            $data2['pickup_date_service_type_'.$data2['service_type_id']]=$Date->formatDateToStandard($data2['pickup_date_service_type_'.$data2['service_type_id']]);
            $data2['pickup_time_service_type_'.$data2['service_type_id']]=$Date->formatTimeToStandard($data2['pickup_time_service_type_'.$data2['service_type_id']]);         
            $data2['return_date_service_type_'.$data2['service_type_id']]=$Date->formatDateToStandard($data2['return_date_service_type_'.$data2['service_type_id']]);
            $data2['return_time_service_type_'.$data2['service_type_id']]=$Date->formatTimeToStandard($data2['return_time_service_type_'.$data2['service_type_id']]);   

            $data2['vehicle_id']=$data['vehicle_id'];
            
            $data2['base_location_distance']=CHBSBookingHelper::getBaseLocationDistance($data2['vehicle_id']);
            $data2['base_location_return_distance']=CHBSBookingHelper::getBaseLocationDistance($data2['vehicle_id'],true);
            
            $Booking=new CHBSBooking();
           
            if(($priceBase['minimum_order_value']>0) && ((int)$priceBase['price_type']===1))
            {
                $priceBooking=$Booking->calculatePrice($data2,$price);
                $difference=$priceBase['minimum_order_value']-$priceBooking['total']['sum']['net']['value'];
                
                if($difference>0)
                {
                    $price['price']['base']['price_initial_value']+=$difference;
                    $priceBooking=$Booking->calculatePrice($data2,$price);
                }
            }
            
            if(((int)$data['booking_form']['meta']['booking_summary_hide_fee']===1) && ($calculateHiddenFee))
            {
                $priceBooking=$Booking->calculatePrice($data2,$price,true);
                
                $price['price']['sum']['net']['value']=number_format($priceBooking['vehicle']['sum']['net']['value'],2,'.','');
                $price['price']['sum']['net']['format']=CHBSPrice::format($price['price']['sum']['net']['value'],CHBSCurrency::getFormCurrency());
                
                $price['price']['sum']['gross']['value']=number_format($priceBooking['vehicle']['sum']['gross']['value'],2,'.','');
                $price['price']['sum']['gross']['format']=CHBSPrice::format($price['price']['sum']['gross']['value'],CHBSCurrency::getFormCurrency());
            }
        }
        
        $price['price']['sum']['gross']['formatHtml']=$this->getPriceFormatHtml($price['price']['sum']['gross']['value']);
        $price['price']['sum']['net']['formatHtml']=$this->getPriceFormatHtml($price['price']['sum']['net']['value']);
        
        $price['price']['other']['net']['price_passenger_adult']['value']=$price['price']['base']['price_passenger_adult_value']*(in_array($data['transfer_type_id'],array(2,3)) ? 2 : 1);
        $price['price']['other']['net']['price_passenger_adult']['formatHtml']=$this->getPriceFormatHtml($price['price']['other']['net']['price_passenger_adult']['value']);
        
        $price['price']['other']['gross']['price_passenger_adult']['value']=CHBSPrice::calculateGross($price['price']['other']['net']['price_passenger_adult']['value'],$price['price']['base']['price_passenger_adult_tax_rate_id']);     
        $price['price']['other']['gross']['price_passenger_adult']['formatHtml']=$this->getPriceFormatHtml($price['price']['other']['gross']['price_passenger_adult']['value']);
         
        $price['price']['other']['net']['price_passenger_children']['value']=$price['price']['base']['price_passenger_children_value']*(in_array($data['transfer_type_id'],array(2,3)) ? 2 : 1);
        $price['price']['other']['net']['price_passenger_children']['formatHtml']=$this->getPriceFormatHtml($price['price']['other']['net']['price_passenger_children']['value']);

        $price['price']['other']['gross']['price_passenger_children']['value']=CHBSPrice::calculateGross($price['price']['other']['net']['price_passenger_children']['value'],$price['price']['base']['price_passenger_children_tax_rate_id']);     
        $price['price']['other']['gross']['price_passenger_children']['formatHtml']=$this->getPriceFormatHtml($price['price']['other']['gross']['price_passenger_children']['value']);
        
        /***/

        return($price);
    }
    
    /**************************************************************************/
    
    function getPriceFormatHtml($price)
    {
        $Currency=new CHBSCurrency();
        
        $currency=$Currency->getCurrency(CHBSCurrency::getFormCurrency());
        
        $t=preg_split('/\\'.$currency['separator'].'/',$price);
        $priceFormatHtml=$t[0].'<span>.'.(empty($t[1]) ? '00' : $t[1]).'</span>';
        
        if(isset($currency['position']) && $currency['position']==='right')
        {
            $priceFormatHtml.=$currency['symbol'];
        }
        else 
        {
            $priceFormatHtml='<span>'.$currency['symbol'].'</span>'.$priceFormatHtml;
        }  
        
        return($priceFormatHtml);
    }
    
    /**************************************************************************/
    
    function getPrice($name,$vehicle,$serviceTypeId,$routeId)
    {
        $vehicleId=$vehicle['post']->ID;
        
        if(in_array($serviceTypeId,array(1,2)))
            return($vehicle['meta'][$name]);
        
        $Route=new CHBSRoute();
        $dictionary=$Route->getDictionary(array('route_id'=>$routeId));
        
        if(!array_key_exists($routeId,$dictionary))
            return($vehicle['meta'][$name]);
        
        $routeVehicle=$dictionary[$routeId]['meta']['vehicle'];

        if(isset($routeVehicle[$vehicleId][$name]))
        {
            if($name=='tax_rate_id')
            {
                if((int)$routeVehicle[$vehicleId][$name]!=-1)
                    return($routeVehicle[$vehicleId][$name]);
            }
            else
            {
                if($routeVehicle[$vehicleId][$name]>0)
                    return($routeVehicle[$vehicleId][$name]);
            }
        }
        
        return($vehicle['meta'][$name]);
    }
    
    /**************************************************************************/
    
    function getVehicleAttribute(&$vehicle)
    {
        $Validation=new CHBSValidation();
        $VehicleAttribute=new CHBSVehicleAttribute();
        
        $dictionary=$VehicleAttribute->getDictionary();

        foreach($vehicle as $vehicleIndex=>$vehicleValue)
        {
            if(!is_array($vehicleValue['meta']['attribute'])) continue;
            
            foreach($vehicleValue['meta']['attribute'] as $vehicleAttributeIndex=>$vehicleAttributeValue)
            {
                if(!isset($dictionary[$vehicleAttributeIndex])) continue;
                
                switch($dictionary[$vehicleAttributeIndex]['meta']['attribute_type'])
                {
                    case 1:
                        
                        if($Validation->isNotEmpty($vehicleAttributeValue))
                            $vehicle[$vehicleIndex]['attribute'][$vehicleAttributeIndex]=array('name'=>get_the_title($vehicleAttributeIndex),'value'=>$vehicleAttributeValue);
                        
                    break;
                
                    case 2:
                    case 3:
                        
                        $value=null;
                        
                        foreach($vehicleAttributeValue as $vehicleAttributeValueValue)
                        {
                            foreach($dictionary[$vehicleAttributeIndex]['meta']['attribute_value'] as $dictionaryAttributeValue)
                            {
                                if($dictionaryAttributeValue['id']===$vehicleAttributeValueValue)
                                {
                                    if(!$Validation->isEmpty($value)) $value.=', ';
                                    $value.=$dictionaryAttributeValue['value'];
                                }
                                
                            }
                        }
                        
                        if($Validation->isNotEmpty($value))
                            $vehicle[$vehicleIndex]['attribute'][$vehicleAttributeIndex]=array('name'=>get_the_title($vehicleAttributeIndex),'value'=>$value);
          
                    break;
                }
            }
        }
    }

    /**************************************************************************/
    
    function manageEditColumns($column)
    {
        $column=array
        (
            'cb'                                                                =>  $column['cb'],
            'thumbnail'                                                         =>  __('Thumbnail','chauffeur-booking-system'),
            'title'                                                             =>  __('Title','chauffeur-booking-system'),
            'vehicle_make_model'                                                =>  __('Vehicle make and model','chauffeur-booking-system'),
            'passenger_bag_count'                                               =>  __('Number of passengers and suitcases','chauffeur-booking-system'),
            'price'                                                             =>  __('Net prices','chauffeur-booking-system'),
        );
   
		return($column);          
    }
    
    /**************************************************************************/
    
    function managePostsCustomColumn($column)
    {
		global $post;
		
        $Length=new CHBSLength();
        $PriceType=new CHBSPriceType();
        
		$meta=CHBSPostMeta::getPostMeta($post);
        
		switch($column) 
		{
			case 'thumbnail':
				
                echo get_the_post_thumbnail($post,array(200,133));
                
			break;
        
			case 'vehicle_make_model':
				
                echo esc_html(trim($meta['vehicle_make'].' '.$meta['vehicle_model']));
                
			break;
        
			case 'passenger_bag_count':
				
                echo 
                '
                    <table class="to-table-post-list">
                        <tr>
                            <td>'.esc_html__('Passsengers','chauffeur-booking-system').'</td>
                            <td>'.$meta['passenger_count'].'</td>
                        </tr>
                        <tr>
                            <td>'.esc_html__('Suitcases','chauffeur-booking-system').'</td>
                            <td>'.$meta['bag_count'].'</td>
                        </tr>                        
                    </table>
                ';
                
			break;
        
			case 'price':

                echo 
                '
                    <table class="to-table-post-list">
                        <tr>
                            <td>'.esc_html__('Price type','chauffeur-booking-system').'</td>
                            <td>'.$PriceType->getPriceTypeName($meta['price_type']).'</td>
                        </tr>  
                ';
                
                if((int)$meta['price_type']===2)
                {
                    echo
                    '
                        <tr>
                            <td>'.esc_html__('Fixed price','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_fixed_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.esc_html__('Fixed price (return)','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_fixed_return_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>  
                        <tr>
                            <td>'.esc_html__('Fixed price (return, new ride)','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_fixed_return_new_ride_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>  
                    ';
                }
                else
                {
                    echo
                    '
                        <tr>
                            <td>'.__('Initial fee','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_initial_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.__('Delivery fee','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_delivery_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.__('Delivery (return) fee','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_delivery_return_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.$Length->label(CHBSOption::getOption('length_unit'),1).'</td>
                            <td>'.CHBSPrice::format($meta['price_distance_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.$Length->label(CHBSOption::getOption('length_unit'),4).'</td>
                            <td>'.CHBSPrice::format($meta['price_distance_return_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.$Length->label(CHBSOption::getOption('length_unit'),5).'</td>
                            <td>'.CHBSPrice::format($meta['price_distance_return_new_ride_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.__('Price per hour','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_hour_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.__('Price per hour (return)','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_hour_return_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.__('Price per hour (return, new ride)','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_hour_return_new_ride_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                    ';
                }
                
                echo
                '
                    <tr>
                        <td>'.__('Price per extra time','chauffeur-booking-system').'</td>
                        <td>'.CHBSPrice::format($meta['price_extra_time_value'],CHBSOption::getOption('currency')).'</td>
                    </tr>
                ';
                
                if((int)$meta['price_type']===1)
                {
                    echo
                    '
                        <tr>
                            <td>'.__('Price per adult','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_passenger_adult_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                        <tr>
                            <td>'.__('Price per child','chauffeur-booking-system').'</td>
                            <td>'.CHBSPrice::format($meta['price_passenger_children_value'],CHBSOption::getOption('currency')).'</td>
                        </tr>
                    ';
                }
                
                echo
                '
                    </table>
                ';
                
			break;
        }
    }
    
    /**************************************************************************/
    
    function manageEditSortableColumns($column)
    {
		return($column);       
    }
    
    /**************************************************************************/
    
    function checkAvailability($dictionary,$pickupDate,$pickupTime,$returnDate,$returnTime,$duration,$data,$bookingFormMeta)
    {
        /***/
        
        $WPML=new CHBSWPML();
        $Date=new CHBSDate();
        $Booking=new CHBSBooking();
        $Validation=new CHBSValidation();
        
        /***/
        
        CHBSHelper::removeUIndex($data,'vehicle_id');
        
        $data['vehicle_id']=$WPML->translateID($data['vehicle_id']);
        
        $dateSet=array();

        if(($Validation->isDate($returnDate)) && ($Validation->isTime($returnTime)))
        {
            $duration/=2;
            $dateSet[0][1][0]=CHBSDate::strtotime($returnDate.' '.$returnTime);
            $dateSet[0][1][1]=CHBSDate::strtotime($returnDate.' '.$returnTime.' + '.$duration.' minute');
        }

        if(($Validation->isDate($pickupDate)) && ($Validation->isTime($pickupTime)))
        {
            $dateSet[0][0][0]=CHBSDate::strtotime($pickupDate.' '.$pickupTime);
            $dateSet[0][0][1]=CHBSDate::strtotime($pickupDate.' '.$pickupTime.' + '.$duration.' minute');
        }

        /***/
     
        if($Validation->isDate($pickupDate))
        {
            $dayNumber=$Date->getDayNumberOfWeek($pickupDate);
            foreach($dictionary as $dictionaryIndex=>$dictionaryValue)
            {
                if(!in_array(-1,$dictionaryValue['meta']['vehicle_availability_day_number']))
                {
                    if(!in_array($dayNumber,$dictionaryValue['meta']['vehicle_availability_day_number']))
                    unset($dictionary[$dictionaryIndex]);
                }
            }
        }
        
        /***/
     
        foreach($dictionary as $dictionaryIndex=>$dictionaryValue)
        {
            $meta=$dictionaryValue['meta'];
            if(!array_key_exists('date_exclude',$meta)) continue;
           
            foreach($meta['date_exclude'] as $dateExcludeValue)
            {
                $dateStart=CHBSDate::strtotime($dateExcludeValue['startDate'].' '.$dateExcludeValue['startTime']);
                $dateStop=CHBSDate::strtotime($dateExcludeValue['stopDate'].' '.$dateExcludeValue['stopTime']);
  
                foreach($dateSet[0] as $date)
                {
                    $b=array_fill(0,4,false);

                    $b[0]=CHBSHelper::valueInRange($date[0],$dateStart,$dateStop);
                    $b[1]=CHBSHelper::valueInRange($date[1],$dateStart,$dateStop);
                    $b[2]=CHBSHelper::valueInRange($dateStart,$date[0],$date[1]);
                    $b[3]=CHBSHelper::valueInRange($dateStop,$date[0],$date[1]);

                    if(in_array(true,$b,true))
                    {
                        unset($dictionary[$dictionaryIndex]);
                        break;                    
                    }
                }
            }
        }
        
        /***/
        
        $vehiclePreventRemove=array();
        
        if((int)$bookingFormMeta['vehicle_in_the_same_booking_passenger_sum_enable']===1)
        {
            $serviceTypeId=(int)$data['service_type_id'];
        
            $b=array();
            
            $b[0]=$Validation->isDate($pickupDate);
            $b[1]=$Validation->isTime($pickupTime);
            $b[2]=(count($bookingFormMeta['location_fixed_pickup_service_type_'.$serviceTypeId]) && in_array($serviceTypeId,array(1,2))) || ($serviceTypeId===3);
            $b[3]=((int)$data['transfer_type_service_type_'.$serviceTypeId]===1 && in_array($serviceTypeId,array(1,2))) || ($serviceTypeId===2);

            if(!in_array(false,$b,true))
            {
                $argument=array
                (
                    'post_type'                                                 =>	$Booking::getCPTName(),
                    'post_status'												=>	'publish',
                    'posts_per_page'                                            =>	-1,
                    'meta_query'                                                =>  array
                    (
                        array
                        (
                            'key'                                               =>  PLUGIN_CHBS_CONTEXT.'_vehicle_id',
                            'value'                                             =>  array_keys($dictionary),
                            'compare'                                           =>  'IN'
                        ),
                        array
                        (
                            'key'                                               =>  PLUGIN_CHBS_CONTEXT.'_booking_status_id',
                            'value'                                             =>  array(1,2),
                            'compare'                                           =>  'IN'
                        ),
                        array
                        (
                            'key'                                               =>  PLUGIN_CHBS_CONTEXT.'_pickup_date',
                            'value'                                             =>  $pickupDate
                        ),                       
                        array
                        (
                            'key'                                               =>  PLUGIN_CHBS_CONTEXT.'_pickup_time',
                            'value'                                             =>  $pickupTime
                        ),                        
                        array
                        (
                            'key'                                               =>  PLUGIN_CHBS_CONTEXT.'_transfer_type_id',
                            'value'                                             =>  1
                        ),   
                         array
                        (
                            'key'                                               =>  PLUGIN_CHBS_CONTEXT.'_passenger_enable',
                            'value'                                             =>  1
                        ),   
                    )
                );
                
                if(in_array($serviceTypeId,array(1,2)))
                {
                    if(array_key_exists('fixed_location_pickup_service_type_'.$serviceTypeId,$data))
                    {
                        array_push
                        (
                            $argument['meta_query'],
                            array
                            (
                                'key'                                           =>  PLUGIN_CHBS_CONTEXT.'_pickup_location_id',
                                'value'                                         =>  $data['fixed_location_pickup_service_type_'.$serviceTypeId]
                            )
                        );
                    }
                    if(array_key_exists('fixed_location_dropoff_service_type_'.$serviceTypeId,$data))
                    {
                        array_push
                        (
                            $argument['meta_query'],
                            array
                            (
                                'key'                                           =>  PLUGIN_CHBS_CONTEXT.'_dropoff_location_id',
                                'value'                                         =>  $data['fixed_location_dropoff_service_type_'.$serviceTypeId]
                            )
                        );
                    }                    
                }
                else
                {
                    array_push
                    (
                        $argument['meta_query'],
                        array
                        (
                            'key'                                               =>  PLUGIN_CHBS_CONTEXT.'_route_id',
                            'value'                                             =>  $data['route_service_type_3']
                        )
                    );                    
                }
            
                global $post;

                CHBSHelper::preservePost($post,$bPost);

                $query=new WP_Query($argument);
                if($query===false) 
                {
                    CHBSHelper::preservePost($post,$bPost,0);
                    return($dictionary); 
                }
                
                /***/
                
                $passengerSumCurrent=0;
                if((CHBSBookingHelper::isPassengerEnable($bookingFormMeta,$serviceTypeId,'adult')) || (CHBSBookingHelper::isPassengerEnable($bookingFormMeta,$serviceTypeId,'children')))
                {
                    if(CHBSBookingHelper::isPassengerEnable($bookingFormMeta,$serviceTypeId,'adult'))
                        $passengerSumCurrent+=$data['passenger_adult_service_type_'.$serviceTypeId];
                    if(CHBSBookingHelper::isPassengerEnable($bookingFormMeta,$serviceTypeId,'children'))
                        $passengerSumCurrent+=$data['passenger_children_service_type_'.$serviceTypeId];            
                }
                
                /***/

                while($query->have_posts())
                {
                    $query->the_post();

                    $meta=CHBSPostMeta::getPostMeta($post);  
                    
                    $passengerSumVehicle=$dictionary[$meta['vehicle_id']]['meta']['passenger_count'];
                    $passengerSumBooking=(int)$meta['passenger_adult_number']+(int)$meta['passenger_children_number'];
                    
                    if($passengerSumVehicle>=($passengerSumCurrent+$passengerSumBooking))
                        $vehiclePreventRemove[]=$meta['vehicle_id'];
                }
                
                CHBSHelper::preservePost($post,$bPost,0);
            }
        }

        /***/
        
        if(($bookingFormMeta['prevent_double_vehicle_booking_enable']==1) && (count($dictionary)))
        {
            $Booking=new CHBSBooking();
            
            $argument=array
            (
                'post_type'														=>	$Booking::getCPTName(),
                'post_status'													=>	'publish',
                'posts_per_page'                                                =>	-1,
                'meta_query'                                                    =>  array
                (
                    array
                    (
                        'key'                                                   =>  PLUGIN_CHBS_CONTEXT.'_vehicle_id',
                        'value'                                                 =>  array_keys($dictionary),
                        'compare'                                               =>  'IN'
                    ),
                    array
                    (
                        'key'                                                   =>  PLUGIN_CHBS_CONTEXT.'_booking_status_id',
                        'value'                                                 =>  array(1,2),
                        'compare'                                               =>  'IN'
                    )
                )
            );
            
            global $post;
            
            CHBSHelper::preservePost($post,$bPost);
        
            $query=new WP_Query($argument);
            if($query===false) 
            {
                CHBSHelper::preservePost($post,$bPost,0);
                return($dictionary); 
            }
            
            while($query->have_posts())
            {
                $query->the_post();
                
                $meta=CHBSPostMeta::getPostMeta($post);
 
                /***/
                
                $bookingReturn=false;
                $bookingDuration=(int)$meta['duration'];
                $bookingExtraTime=(int)$meta['extra_time_value'];
                
                if(in_array($meta['service_type_id'],array(1,3)))
                {
                    if(in_array($meta['transfer_type_id'],array(3)))
                    {
                        $bookingReturn=true;
                        $bookingExtraTime=ceil($bookingExtraTime/2);
                    }
                }       
                
                $dateSet[1][0][0]=CHBSDate::strtotime($meta['pickup_date'].' '.$meta['pickup_time']);
                $dateSet[1][0][1]=CHBSDate::strtotime($meta['pickup_date'].' '.$meta['pickup_time'].' + '.($bookingDuration+$bookingExtraTime+(int)$bookingFormMeta['booking_vehicle_interval']).' minute');
                
                if($bookingReturn)
                {
                    $dateSet[1][1][0]=CHBSDate::strtotime($meta['return_date'].' '.$meta['return_time']);
                    $dateSet[1][1][1]=CHBSDate::strtotime($meta['return_date'].' '.$meta['return_time'].' + '.($bookingDuration+$bookingExtraTime+(int)$bookingFormMeta['booking_vehicle_interval']).' minute');                 
                }
                
                /***/
                
                foreach($dateSet[0] as $dateCurrent)
                {
                    foreach($dateSet[1] as $dateBooking)
                    {
                        $b=array_fill(0,4,false);

                        $b[0]=CHBSHelper::valueInRange($dateCurrent[0],$dateBooking[0],$dateBooking[1]);
                        $b[1]=CHBSHelper::valueInRange($dateCurrent[1],$dateBooking[0],$dateBooking[1]);
                        $b[2]=CHBSHelper::valueInRange($dateBooking[0],$dateCurrent[0],$dateCurrent[1]);
                        $b[3]=CHBSHelper::valueInRange($dateBooking[1],$dateCurrent[0],$dateCurrent[1]);

                        if(in_array(true,$b,true))
                        {
                            if(!in_array($meta['vehicle_id'],$vehiclePreventRemove))
                                unset($dictionary[$meta['vehicle_id']]);
                            
                            continue;                    
                        }                        
                    }
                }
            }            
            
            CHBSHelper::preservePost($post,$bPost,0);
        }
        
        /***/
        
        return($dictionary);
    }
    
    /**************************************************************************/
    
    function getVehicleList($data=array())
    {
		$argument=array
		(
			'post_type'                                                         =>	self::getCPTName(),
			'post_status'                                                       =>	'publish',
			'posts_per_page'                                                    =>  (int)get_option('posts_per_page'),
			'paged'                                                             =>	1,
			'orderby'                                                           =>  'post_title',
			'order'                                                             =>	'asc'
		);
        
        if((isset($data['vehicle_id'])) && (is_array($data['vehicle_id'])) && (count($data['vehicle_id'])))
            $argument['post__in']=$data['vehicle_id'];
        if(isset($data['paged']))
            $argument['paged']=$data['paged'];
        if(isset($data['posts_per_page']))
            $argument['posts_per_page']=$data['posts_per_page'];        
        
        
        $Validation=new CHBSValidation();
        
        $make=CHBSHelper::getGetValue('make',false);
		$category=(int)CHBSHelper::getGetValue('category',false);
		
        /**/
        
        if($category>0)
        {
            $argument['tax_query']=array
            (
                array
                (
                    'taxonomy'                                                  =>  self::getCPTCategoryName(),
                    'field'                                                     =>  'term_id',
                    'terms'                                                     =>  array($category),
                    'operator'                                                  =>  'IN'
                )
            );
        }
        
        if($Validation->isNotEmpty($make))
        {
            $argument['meta_query']=array
            (
                array
                (
                    'key'                                                       =>  PLUGIN_CHBS_CONTEXT.'_vehicle_make',
                    'value'                                                     =>  $_GET['make'],
                    'compare'                                                   =>  'LIKE',
                )
            );      
        }
        
        /***/
        
        if(get_query_var('paged')) $argument['paged']=get_query_var('paged');
        
        /***/
   
		$query=new WP_Query($argument);

		return($query);        
    }
    
    /**************************************************************************/
    
    function restrictManagePosts()
    {
 		if(!is_admin()) return;
		if(CHBSHelper::getGetValue('post_type',false)!==self::getCPTName()) return;       
        
        $html=null;
        
        /***/
        
        $VehicleCompany=new CHBSVehicleCompany();
        $vehicleCompanyDictionary=$VehicleCompany->getDictionary();
        
        $dictionary=array(0=>__('- Not set -','chauffeur-booking-system'));
        foreach($vehicleCompanyDictionary as $index=>$value)
            $dictionary[$index]=$value['post']->post_title;
        
 		foreach($dictionary as $index=>$value)
			$html.='<option value="'.(int)$index.'" '.(((int)CHBSHelper::getGetValue('vehicle_company_id',false)==$index) ? 'selected' : null).'>'.esc_html($value).'</option>';
		
		$html=
		'
			<select name="vehicle_company_id">
				'.$html.'
			</select>
		';
        
        /***/
        
        echo $html;
    }
    
    /**************************************************************************/
    
    function parseQuery($query)
    {
		if(!is_admin()) return;
		if(CHBSHelper::getGetValue('post_type',false)!==self::getCPTName()) return;
		if($query->query['post_type']!==self::getCPTName()) return;       
        
        /***/
        
        $metaQuery=array();
        $Validation=new CHBSValidation();
        
        /***/
        
		$vehicleCompanyId=CHBSHelper::getGetValue('vehicle_company_id',false);
		if($Validation->isEmpty($vehicleCompanyId)) $vehicleCompanyId=0;

		if($vehicleCompanyId!=0)
		{
			array_push($metaQuery,array
			(
				'key'															=>	PLUGIN_CHBS_CONTEXT.'_vehicle_company_id',
				'value'															=>	$vehicleCompanyId,
				'compare'														=>	'='
			));
		}  
        
        /***/
        
		if(count($metaQuery)) $query->set('meta_query',$metaQuery);
    }
    
    /**************************************************************************/
}

/******************************************************************************/
/******************************************************************************/
