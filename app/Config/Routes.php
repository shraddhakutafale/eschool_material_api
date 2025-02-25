<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$allowed_origins = [
  'http://localhost:4200',
  'https://shritej.in',
  'https://www.shritej.in',
  'http://shritej.in',
  'https://admin.exiaa.com'
];
$routes->options('(:any)', function () use ($allowed_origins){
  if (isset($_SERVER['HTTP_ORIGIN']) && in_array($_SERVER['HTTP_ORIGIN'], $allowed_origins)) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
  }
    
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
    header('Access-Control-Allow-Credentials: true');
    http_response_code(204); // No Content
    exit();
});
$routes->group('api', ['namespace' => 'App\Controllers'], function ($routes) {

    $routes->get('user/getall', 'User::index',['filter' => 'authFilter']); 
    $routes->get('user/view/(:segment)', 'User::show/$1',['filter' => 'authFilter']); 
    $routes->post('user/create', 'User::create',['filter' => 'authFilter']); 
    $routes->post('user/update', 'User::update/$1',['filter' => 'authFilter']); 
    $routes->post('user/delete', 'User::delete',['filter' => 'authFilter']); 
    $routes->post('user/login', 'User::login');
    $routes->get('user/profile', 'User::profile',['filter' => 'authFilter']);
    $routes->get('user/usermenu', 'User::menu',['filter' => 'authFilter']);
    $routes->post('user/register', 'User::register');

    $routes->get('user/getallrole', 'User::getAllRole',['filter' => 'authFilter']); 
    $routes->post('user/createrole', 'User::createRole',['filter' => 'authFilter']); 
    $routes->post('user/updaterole', 'User::updateRole/$1',['filter' => 'authFilter']);
    $routes->post('user/deleterole', 'User::deleteRole',['filter' => 'authFilter']);




    $routes->get('user/getallright', 'User::getAllRight',['filter' => 'authFilter']); 
    $routes->post('user/createright', 'User::createRight',['filter' => 'authFilter']);
    $routes->post('user/updateright', 'User::updateRight/$1',['filter' => 'authFilter']); 
    $routes->post('user/deleteright', 'User::deleteRight',['filter' => 'authFilter']); 

    $routes->get('user/getalltenant', 'User::getAllTenant',['filter' => 'authFilter']); 
    $routes->post('user/createtenant', 'User::createTenant',['filter' => 'authFilter']);
    $routes->post('user/updatetenant', 'User::updateTenant/$1',['filter' => 'authFilter']); 
    $routes->post('user/deletetenant', 'User::deleteTenant',['filter' => 'authFilter']); 

    $routes->get('user/getallbusiness', 'User::getAllBusiness',['filter' => 'authFilter']);
    $routes->post('user/createbusiness', 'User::createBusiness',['filter' => 'authFilter']); 
    $routes->post('user/updatebusiness', 'User::updateBusiness/$1',['filter' => 'authFilter']); 
    $routes->post('user/deletebusiness', 'User::deleteBusiness',['filter' => 'authFilter']); 

 

    $routes->get('user/getallbusinesscategory', 'User::getAllBusinesscategory',['filter' => 'authFilter']); // Get all businesscategory
    $routes->get('user/getalltenantname', 'User::getAllTenantname',['filter' => 'authFilter']);




    // Routes for roles
    $routes->post('user/getrolespaging', 'User::getRolesPaging',['filter' => 'authFilter']);
    $routes->post('user/getrightspaging', 'User::getRightsPaging',['filter' => 'authFilter']);
    $routes->post('user/gettenantspaging', 'User::getTenantsPaging',['filter' => 'authFilter']);
    $routes->post('user/getbusinessespaging', 'User::getBusinessesPaging',['filter' => 'authFilter']);



    // routes for settings
    $routes->get('setting/getallfirebase', 'Setting::index',['filter' => 'authFilter']); 
    $routes->get('setting/view/(:segment)', 'Setting::show/$1',['filter' => 'authFilter']); 
    $routes->post('setting/createfirebase', 'Setting::createFirebase',['filter' => 'authFilter']); 
    $routes->post('setting/updatefirebase', 'Setting::updateFirebase/$1',['filter' => 'authFilter']);
    $routes->post('setting/deletefirebase', 'Setting::deleteFirebase',['filter' => 'authFilter']);


    

    $routes->get('setting/getallsms', 'Setting::getAllSms',['filter' => 'authFilter']);
    $routes->post('setting/createsms', 'Setting::createSms',['filter' => 'authFilter']); 
    $routes->post('setting/updatesms', 'Setting::updateSms/$1',['filter' => 'authFilter']); 
    $routes->post('setting/deletesms', 'Setting::deleteSms',['filter' => 'authFilter']); 


    
    $routes->get('setting/getallsmtp', 'Setting::getAllSmtp',['filter' => 'authFilter']);
    $routes->post('setting/createsmtp', 'Setting::createSmtp',['filter' => 'authFilter']); 
    $routes->post('setting/updatesmtp', 'Setting::updateSmtp/$1',['filter' => 'authFilter']); 
    $routes->post('setting/deletesmtp', 'Setting::deleteSmtp',['filter' => 'authFilter']); 



       // Routes for Slide
       $routes->get('slide/getall', 'Slide::index',['filter' => ['authFilter', 'tenantFilter']]);
       $routes->post('slide/getallpaging', 'Slide::getSlidesPaging',['filter' => ['authFilter', 'tenantFilter']]);
       $routes->get('slide/view/(:segment)', 'Slide::show/$1',['filter' => 'authFilter']);
       $routes->post('slide/create', 'Slide::create',['filter' => ['authFilter','tenantFilter']]);
       $routes->post('slide/update', 'Slide::update',['filter' => ['authFilter','tenantFilter']]);
       $routes->get('slide/getallwebsite', 'Slide::getSlidesWebsite',['filter' => ['tenantFilter']]);
       $routes->post('slide/delete', 'Slide::delete',['filter' => ['authFilter','tenantFilter']]); 

    // Routes for Business
    $routes->get('business/getall', 'Business::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('business/getallpaging', 'Business::getBusinessesPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('business/getallbyuser/(:segment)', 'Business::getAllBusinessByUser/$1',['filter' => 'authFilter']);
    $routes->post('business/create', 'Business::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('business/update', 'Business::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('business/getallwebsite', 'Business::getBusinessesWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('business/delete', 'Business::delete',['filter' => ['authFilter','tenantFilter']]); 

    // Routes for courses
    $routes->get('course/getall', 'Course::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('course/getallpaging', 'Course::getCoursesPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('course/view/(:segment)', 'Course::show/$1',['filter' => 'authFilter']);
    $routes->post('course/create', 'Course::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/update', 'Course::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/delete', 'Course::delete',['filter' => ['authFilter','tenantFilter']]); // Get all courses for website
    $routes->get('course/getallwebsite', 'Course::getCoursesWebsite',['filter' => ['tenantFilter']]);

    $routes->get('course/getallshift', 'Course::getAllShift',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('course/createshift', 'Course::createShift',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/updateshift', 'Course::updateShift',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/deleteshift', 'Course::deleteShift',['filter' => ['authFilter','tenantFilter']]);

    $routes->get('course/getallsubject', 'Course::getAllSubject',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('course/createsubject', 'Course::createSubject',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/updatesubject', 'Course::updateSubject',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/deletesubject', 'Course::deleteSubject',['filter' => ['authFilter','tenantFilter']]);

    $routes->get('course/getallfee', 'Course::getAllFee',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('course/createfee', 'Course::createFee',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/updatefee', 'Course::updateFee',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('course/deletefee', 'Course::deleteFee',['filter' => ['authFilter','tenantFilter']]);
    
    //Routes for event
    $routes->get('event/getall', 'Event::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('event/getallpaging', 'Event::getEventsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('event/view/(:segment)', 'Event::show/$1',['filter' => 'authFilter']);
    $routes->post('event/create', 'Event::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('event/update', 'Event::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('event/getallwebsite', 'Event::getEventsWebsite',['filter' => ['tenantFilter']]); // Get all event for website
    $routes->post('event/delete', 'Event::delete',['filter' => ['authFilter','tenantFilter']]); 

    //Routes for customer
    $routes->get('customer/getall', 'Customer::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('customer/getallpaging', 'Customer::getCustomersPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('customer/view/(:segment)', 'Customer::show/$1',['filter' => 'authFilter']);
    $routes->post('customer/create', 'Customer::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('customer/update', 'Customer::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('customer/getallwebsite', 'Customer::getCustomersWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('customer/delete', 'Customer::delete',['filter' => ['authFilter','tenantFilter']]); 

    
    //Routes for member
    $routes->get('member/getall', 'Member::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('member/getallpaging', 'Member::getMembersPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('member/view/(:segment)', 'Member::show/$1',['filter' => 'authFilter']);
    $routes->post('member/create', 'Member::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('member/update', 'Member::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('member/getallwebsite', 'Member::getMembersWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('member/delete', 'Member::delete',['filter' => ['authFilter','tenantFilter']]); 
    $routes->post('member/createweb', 'Member::createWeb',['filter' => 'tenantFilter']);
    $routes->post('donate/createweb', 'Donation::createWeb',['filter' => 'tenantFilter']);


    //Routes for Donation
    $routes->get('donation/getall', 'Donation::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('donation/view/(:segment)', 'Donation::show/$1',['filter' => 'authFilter']);
    $routes->post('donation/getallpaging', 'Donation::getDonationsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('donation/create', 'Donation::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('donation/getallwebsite', 'Donation::getDonationsWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('donation/update', 'Donation::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('donation/delete', 'Donation::delete',['filter' => ['authFilter','tenantFilter']]);

     
    //Routes for student
    $routes->get('student/getall', 'Student::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('student/getallpaging', 'Student::getStudentsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('student/view/(:segment)', 'Student::show/$1',['filter' => 'authFilter']);
    $routes->post('student/create', 'Student::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('student/update', 'Student::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('student/getallwebsite', 'Student::getStudentsWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('student/delete', 'Student::delete',['filter' => ['authFilter','tenantFilter']]); 

     //Routes for item
     $routes->get('item/getall', 'Item::index',['filter' => ['authFilter', 'tenantFilter']]);
     $routes->post('item/getallpaging', 'Item::getItemsPaging',['filter' => ['authFilter', 'tenantFilter']]);
     $routes->get('item/view/(:segment)', 'Item::show/$1',['filter' => ['authFilter','tenantFilter']]);
     $routes->post('item/create', 'Item::create',['filter' => ['authFilter','tenantFilter']]);
     $routes->post('item/update', 'Item::update',['filter' => ['authFilter','tenantFilter']]);
     $routes->get('item/getallwebsite', 'Item::getItemsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
     $routes->post('item/delete', 'Item::delete',['filter' => ['authFilter','tenantFilter']]);
     $routes->get('item/getallcategory', 'Item::getAllItemCategory',['filter' => ['authFilter','tenantFilter']]); 
     $routes->post('item/createcategory', 'Item::createCategory',['filter' => ['authFilter','tenantFilter']]);
     $routes->get('item/getallunit', 'Item::getAllUnit',['filter' => ['authFilter','tenantFilter']]);

      //Routes for stock
      $routes->get('stock/getall', 'Stock::index',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->post('stock/getallpaging', 'Stock::getStocksPaging',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->get('stock/view/(:segment)', 'Stock::show/$1',['filter' => 'authFilter']);
      $routes->post('stock/create', 'Stock::create',['filter' => ['authFilter','tenantFilter']]);
      $routes->post('stock/update', 'Stock::update',['filter' => ['authFilter','tenantFilter']]);
      $routes->get('stock/getallwebsite', 'Stock::getStocksWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
      $routes->post('stock/delete', 'Stock::delete',['filter' => ['authFilter','tenantFilter']]); 

        //Routes for service
        $routes->get('service/getall', 'Service::index',['filter' => ['authFilter', 'tenantFilter']]);
        $routes->post('service/getallpaging', 'Service::getServicesPaging',['filter' => ['authFilter', 'tenantFilter']]); //add s
        $routes->get('service/view/(:segment)', 'Service::show/$1',['filter' => 'authFilter']);
        $routes->post('service/create', 'Service::create',['filter' => ['authFilter','tenantFilter']]);
        $routes->post('service/update', 'Service::update',['filter' => ['authFilter','tenantFilter']]);
        $routes->get('service/getallwebsite', 'Service::getServicesWebsite',['filter' => ['tenantFilter']]); //add s 
        $routes->post('service/delete', 'Service::delete',['filter' => ['authFilter','tenantFilter']]);
      
       //Routes for gallery
       $routes->get('gallery/getall', 'Gallery::index',['filter' => ['authFilter', 'tenantFilter']]);
       $routes->post('gallery/getallpaging', 'Gallery::getGallerysPaging',['filter' => ['authFilter', 'tenantFilter']]);
       $routes->get('gallery/view/(:segment)', 'Gallery::show/$1',['filter' => 'authFilter']);
       $routes->post('gallery/create', 'Gallery::create',['filter' => ['authFilter','tenantFilter']]);
       $routes->post('gallery/update', 'Gallery::update',['filter' => ['authFilter','tenantFilter']]);
       $routes->get('gallery/getallwebsite', 'Gallery::getGallerysWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
       $routes->post('gallery/delete', 'Gallery::delete',['filter' => ['authFilter','tenantFilter']]); 
  
     //Routes for quotation
      $routes->get('quotation/getall', 'Quotation::index',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->post('quotation/getallpaging', 'Quotation::getQuotationsPaging',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->get('quotation/view/(:segment)', 'Quotation::show/$1',['filter' => 'authFilter']);
      $routes->post('quotation/create', 'Quotation::create',['filter' => ['authFilter','tenantFilter']]);
      $routes->post('quotation/update', 'Quotation::update',['filter' => ['authFilter','tenantFilter']]);
      $routes->get('quotation/getallwebsite', 'Quotation::getQuotationsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
      $routes->post('quotation/delete', 'Quotation::delete',['filter' => ['authFilter','tenantFilter']]); 


       //Routes for Purchase Order (PO)
       $routes->get('po/getall', 'Po::index',['filter' => ['authFilter', 'tenantFilter']]);
       $routes->post('po/getallpaging', 'Po::getPosPaging',['filter' => ['authFilter', 'tenantFilter']]);
       $routes->get('po/view/(:segment)', 'Po::show/$1',['filter' => 'authFilter']);
       $routes->post('po/create', 'Po::create',['filter' => ['authFilter','tenantFilter']]);
       $routes->post('po/update', 'Po::update',['filter' => ['authFilter','tenantFilter']]);
       $routes->get('po/getallwebsite', 'Po::getPosWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
       $routes->post('po/delete', 'Po::delete',['filter' => ['authFilter','tenantFilter']]); 
      
         //Routes for sale
         $routes->get('sale/getall', 'Sale::index',['filter' => ['authFilter', 'tenantFilter']]);
         $routes->post('sale/getallpaging', 'Sale::getSalesPaging',['filter' => ['authFilter', 'tenantFilter']]);
         $routes->get('sale/view/(:segment)', 'Sale::show/$1',['filter' => 'authFilter']);
         $routes->post('sale/create', 'Sale::create',['filter' => ['authFilter','tenantFilter']]);
         $routes->post('sale/update', 'Sale::update',['filter' => ['authFilter','tenantFilter']]);
         $routes->get('sale/getallwebsite', 'Sale::getSalesWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
         $routes->post('sale/delete', 'Sale::delete',['filter' => ['authFilter','tenantFilter']]); 

         //Routes for Team
         $routes->get('team/getall', 'Team::index',['filter' => ['authFilter', 'tenantFilter']]);
         $routes->post('team/getallpaging', 'Team::getTeamsPaging',['filter' => ['authFilter', 'tenantFilter']]);
         $routes->get('team/view/(:segment)', 'Team::show/$1',['filter' => 'authFilter']);
         $routes->post('team/create', 'Team::create',['filter' => ['authFilter','tenantFilter']]);
         $routes->post('team/update', 'Team::update',['filter' => ['authFilter','tenantFilter']]);
         $routes->get('team/getallwebsite', 'Team::getTeamsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
         $routes->post('team/delete', 'Team::delete',['filter' => ['authFilter','tenantFilter']]); 
        

         //Routes for service
        $routes->get('blog/getall', 'blog::index',['filter' => ['authFilter', 'tenantFilter']]);
        $routes->post('blog/getallpaging', 'blog::getBlogsPaging',['filter' => ['authFilter', 'tenantFilter']]); //add s
        $routes->get('blog/view/(:segment)', 'Blog::show/$1',['filter' => 'authFilter']);
        $routes->post('blog/create', 'Blog::create',['filter' => ['authFilter','tenantFilter']]);
        $routes->post('blog/update', 'Blog::update',['filter' => ['authFilter','tenantFilter']]);
        $routes->get('blog/getallwebsite', 'Blog::getBlogsWebsite',['filter' => ['tenantFilter']]); //add s 
        $routes->post('blog/delete', 'Blog::delete',['filter' => ['authFilter','tenantFilter']]);
      

                   
  //Routes for staff
  $routes->get('staff/getall', 'Staff::index',['filter' => ['authFilter', 'tenantFilter']]);
  $routes->post('staff/getallpaging', 'Staff::getStaffsPaging',['filter' => ['authFilter', 'tenantFilter']]);
  $routes->get('staff/view/(:segment)', 'Staff::show/$1',['filter' => 'authFilter']);
  $routes->post('staff/create', 'Staff::create',['filter' => ['authFilter','tenantFilter']]);
  $routes->post('staff/update', 'Staff::update',['filter' => ['authFilter','tenantFilter']]);
  $routes->get('staff/getallwebsite', 'Staff::getStaffsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
  $routes->post('staff/delete', 'Staff::delete',['filter' => ['authFilter','tenantFilter']]);


         //Routes for vendor
         $routes->get('vendor/getall', 'Vendor::index',['filter' => ['authFilter', 'tenantFilter']]);
         $routes->post('vendor/getallpaging', 'Vendor::getVendorsPaging',['filter' => ['authFilter', 'tenantFilter']]);
         $routes->get('vendor/view/(:segment)', 'Vendor::show/$1',['filter' => 'authFilter']);
         $routes->post('vendor/create', 'Vendor::create',['filter' => ['authFilter','tenantFilter']]);
         $routes->post('vendor/update', 'Vendor::update',['filter' => ['authFilter','tenantFilter']]);
         $routes->get('vendor/getallwebsite', 'Vendor::getVendorsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
         $routes->post('vendor/delete', 'Vendor::delete',['filter' => ['authFilter','tenantFilter']]);

              
    //Routes for Purchase
    $routes->get('purchase/getall', 'Purchase::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('purchase/getallpaging', 'Purchase::getPurchasesPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('purchase/view/(:segment)', 'Purchase::show/$1',['filter' => 'authFilter']);
    $routes->post('purchase/create', 'Purchase::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('purchase/update', 'Purchase::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('purchase/getallwebsite', 'Purchase::getPurchasesWebsite',['filter' => ['tenantFilter']]); // Get all purchase for website
    $routes->post('purchase/delete', 'Purchase::delete',['filter' => ['authFilter','tenantFilter']]);

      //Routes for lead
      $routes->get('lead/getall', 'Lead::index',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->post('lead/getallpaging', 'Lead::getLeadsPaging',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->get('lead/view/(:segment)', 'Lead::show/$1',['filter' => 'authFilter']);
      $routes->post('lead/create', 'Lead::create',['filter' => ['authFilter','tenantFilter']]);
      $routes->post('lead/update', 'Lead::update',['filter' => ['authFilter','tenantFilter']]);
      $routes->get('lead/getallwebsite', 'Lead::getLeadsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
      $routes->post('lead/delete', 'Lead::delete',['filter' => ['authFilter','tenantFilter']]); 

      //Routes for order
      $routes->get('order/getall', 'Order::index',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->post('order/getallpaging', 'Order::getOrdersPaging',['filter' => ['authFilter', 'tenantFilter']]);
      $routes->post('order/create', 'Order::create',['filter' => ['authFilter','tenantFilter']]);
      $routes->post('order/update', 'Order::update',['filter' => ['authFilter','tenantFilter']]);
      $routes->post('order/delete', 'Order::delete',['filter' => ['authFilter','tenantFilter']]);
      $routes->get('order/getlastorder', 'Order::getLastOrder',['filter' => ['authFilter','tenantFilter']]); 

      // Routes for website
      $routes->get('item/getallcategoryweb', 'Item::getAllCategoryWeb',['filter' => 'tenantFilter']);
      $routes->get('item/getallitembycategoryweb/(:num)', 'Item::getAllItemByCategoryWeb/$1',['filter' => 'tenantFilter']);
      $routes->get('item/getallitembytag/(:segment)', 'Item::getAllItemByTagWeb/$1',['filter' => 'tenantFilter']);
      $routes->get('item/getfouritembycategoryweb', 'Item::getFourItemByCategoryWeb',['filter' => 'tenantFilter']);
      $routes->get('item/getfouritembytag/(:segment)', 'Item::getFourItemByTagWeb/$1',['filter' => 'tenantFilter']);
      $routes->post('item/viewweb', 'Item::show',['filter' => ['tenantFilter']]);

      // Routes for Quote
      $routes->post('quote/sendemail', 'Quote::sendQuoteEmail',['filter' => 'tenantFilter']);

      //


});