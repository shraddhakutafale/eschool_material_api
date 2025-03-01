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

  $routes->group('user', function ($routes) {
    $routes->get('getall', 'User::index',['filter' => 'authFilter']); 
    $routes->get('view/(:segment)', 'User::show/$1',['filter' => 'authFilter']); 
    $routes->post('create', 'User::create',['filter' => 'authFilter']); 
    $routes->post('update', 'User::update/$1',['filter' => 'authFilter']); 
    $routes->post('delete', 'User::delete',['filter' => 'authFilter']); 
    $routes->post('login', 'User::login');
    $routes->get('profile', 'User::profile',['filter' => 'authFilter']);
    $routes->get('usermenu', 'User::menu',['filter' => 'authFilter']);
    $routes->post('register', 'User::register');

    $routes->get('getallrole', 'User::getAllRole',['filter' => 'authFilter']); 
    $routes->post('createrole', 'User::createRole',['filter' => 'authFilter']); 
    $routes->post('updaterole', 'User::updateRole/$1',['filter' => 'authFilter']);
    $routes->post('deleterole', 'User::deleteRole',['filter' => 'authFilter']);

    $routes->get('getallright', 'User::getAllRight',['filter' => 'authFilter']); 
    $routes->post('createright', 'User::createRight',['filter' => 'authFilter']);
    $routes->post('updateright', 'User::updateRight/$1',['filter' => 'authFilter']); 
    $routes->post('deleteright', 'User::deleteRight',['filter' => 'authFilter']); 

    $routes->get('getalltenant', 'User::getAllTenant',['filter' => 'authFilter']); 
    $routes->post('createtenant', 'User::createTenant',['filter' => 'authFilter']);
    $routes->post('updatetenant', 'User::updateTenant/$1',['filter' => 'authFilter']); 
    $routes->post('deletetenant', 'User::deleteTenant',['filter' => 'authFilter']); 

    $routes->get('getallbusiness', 'User::getAllBusiness',['filter' => 'authFilter']); // Get all items
    $routes->post('createbusiness', 'User::createBusiness',['filter' => 'authFilter']); // Create a new item
    $routes->post('updatebusiness', 'User::updateBusiness/$1',['filter' => 'authFilter']); // Update an item
    $routes->post('deletebusiness', 'User::deleteBusiness',['filter' => 'authFilter']); // Delete an item
    $routes->post('assignbusiness', 'User::assignBusiness',['filter' => 'authFilter']); // Get all businesscategory

    $routes->get('getallbusinesscategory', 'User::getAllBusinesscategory',['filter' => 'authFilter']); // Get all businesscategory
    $routes->get('getalltenantname', 'User::getAllTenantname',['filter' => 'authFilter']);
    $routes->post('getallpermissionbycategory', 'User::getAllPermissionByCategory',['filter' => 'authFilter']);
    $routes->post('updatepermissions', 'User::updatePermissions',['filter' => 'authFilter']);

     // Routes for roles
    $routes->post('getrolespaging', 'User::getRolesPaging',['filter' => 'authFilter']);
    $routes->post('getrightspaging', 'User::getRightsPaging',['filter' => 'authFilter']);
    $routes->post('gettenantspaging', 'User::getTenantsPaging',['filter' => 'authFilter']);
    $routes->post('getbusinessespaging', 'User::getBusinessesPaging',['filter' => 'authFilter']);

  });

  $routes->group('setting', function ($routes) {
    // routes for settings
    $routes->get('getallfirebase', 'Setting::index',['filter' => 'authFilter']); 
    $routes->get('view/(:segment)', 'Setting::show/$1',['filter' => 'authFilter']); 
    $routes->post('createfirebase', 'Setting::createFirebase',['filter' => 'authFilter']); 
    $routes->post('updatefirebase', 'Setting::updateFirebase/$1',['filter' => 'authFilter']);
    $routes->post('deletefirebase', 'Setting::deleteFirebase',['filter' => 'authFilter']);

    $routes->get('getallsms', 'Setting::getAllSms',['filter' => 'authFilter']);
    $routes->post('createsms', 'Setting::createSms',['filter' => 'authFilter']); 
    $routes->post('updatesms', 'Setting::updateSms/$1',['filter' => 'authFilter']); 
    $routes->post('deletesms', 'Setting::deleteSms',['filter' => 'authFilter']); 
    
    $routes->get('getallsmtp', 'Setting::getAllSmtp',['filter' => 'authFilter']);
    $routes->post('createsmtp', 'Setting::createSmtp',['filter' => 'authFilter']); 
    $routes->post('updatesmtp', 'Setting::updateSmtp/$1',['filter' => 'authFilter']); 
    $routes->post('deletesmtp', 'Setting::deleteSmtp',['filter' => 'authFilter']);

  });
  
  $routes->group('slide', function ($routes) {
    // Routes for Slide
    $routes->get('getall', 'Slide::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Slide::getSlidesPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Slide::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Slide::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Slide::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Slide::getSlidesWebsite',['filter' => ['tenantFilter']]);
    $routes->post('delete', 'Slide::delete',['filter' => ['authFilter','tenantFilter']]); 
  });

  $routes->group('business', function ($routes) {
     // Routes for Business
     $routes->get('getall', 'Business::index',['filter' => ['authFilter', 'tenantFilter']]);
     $routes->post('getallpaging', 'Business::getBusinessesPaging',['filter' => ['authFilter', 'tenantFilter']]);
     $routes->get('getallbyuser/(:segment)', 'Business::getAllBusinessByUser/$1',['filter' => 'authFilter']);
     $routes->post('create', 'Business::create',['filter' => ['authFilter','tenantFilter']]);
     $routes->post('update', 'Business::update',['filter' => ['authFilter','tenantFilter']]);
     $routes->get('getallwebsite', 'Business::getBusinessesWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
     $routes->post('delete', 'Business::delete',['filter' => ['authFilter','tenantFilter']]); 
  });
  
  $routes->group('course', function ($routes) {
    // Routes for courses
    $routes->get('getall', 'Course::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Course::getCoursesPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Course::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Course::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Course::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('delete', 'Course::delete',['filter' => ['authFilter','tenantFilter']]); // Get all courses for website
    $routes->get('getallwebsite', 'Course::getCoursesWebsite',['filter' => ['tenantFilter']]);

    $routes->get('getallshift', 'Course::getAllShift',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('createshift', 'Course::createShift',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('updateshift', 'Course::updateShift',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('deleteshift', 'Course::deleteShift',['filter' => ['authFilter','tenantFilter']]);

    $routes->get('getallsubject', 'Course::getAllSubject',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('createsubject', 'Course::createSubject',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('updatesubject', 'Course::updateSubject',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('deletesubject', 'Course::deleteSubject',['filter' => ['authFilter','tenantFilter']]);

    $routes->get('getallfee', 'Course::getAllFee',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('createfee', 'Course::createFee',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('updatefee', 'Course::updateFee',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('deletefee', 'Course::deleteFee',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('event', function ($routes) {
    //Routes for event
    $routes->get('getall', 'Event::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Event::getEventsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Event::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Event::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Event::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Event::getEventsWebsite',['filter' => ['tenantFilter']]); // Get all event for website
    $routes->post('delete', 'Event::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('customer', function ($routes) {
    //Routes for customer
    $routes->get('getall', 'Customer::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Customer::getCustomersPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Customer::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Customer::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Customer::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Customer::getCustomersWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('delete', 'Customer::delete',['filter' => ['authFilter','tenantFilter']]);
  });
   
  $routes->group('member', function ($routes) {
    //Routes for member
    $routes->get('getall', 'Member::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Member::getMembersPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Member::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Member::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Member::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Member::getMembersWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('delete', 'Member::delete',['filter' => ['authFilter','tenantFilter']]); 
    $routes->post('createweb', 'Member::createWeb',['filter' => 'tenantFilter']);
    
  });
  $routes->post('donate/createweb', 'Donation::createWeb',['filter' => 'tenantFilter']);
  $routes->group('donation', function ($routes) {
    //Routes for Donation
    $routes->get('getall', 'Donation::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Donation::show/$1',['filter' => 'authFilter']);
    $routes->post('getallpaging', 'Donation::getDonationsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('create', 'Donation::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Donation::getDonationsWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('update', 'Donation::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('delete', 'Donation::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('student', function ($routes) {
    //Routes for student
    $routes->get('getall', 'Student::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Student::getStudentsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Student::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Student::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Student::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Student::getStudentsWebsite',['filter' => ['tenantFilter']]); // Get all customer for website
    $routes->post('delete', 'Student::delete',['filter' => ['authFilter','tenantFilter']]);
  });    

  $routes->group('item', function ($routes) {
    //Routes for item
    $routes->get('getall', 'Item::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Item::getItemsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Item::show/$1',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('create', 'Item::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Item::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Item::getItemsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Item::delete',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallcategory', 'Item::getAllItemCategory',['filter' => ['authFilter','tenantFilter']]); 
    $routes->post('createcategory', 'Item::createCategory',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallunit', 'Item::getAllUnit',['filter' => ['authFilter','tenantFilter']]);
    // Routes for website
    $routes->get('getallcategoryweb', 'Item::getAllCategoryWeb',['filter' => 'tenantFilter']);
    $routes->get('getallitembycategoryweb/(:num)', 'Item::getAllItemByCategoryWeb/$1',['filter' => 'tenantFilter']);
    $routes->get('getallitembytag/(:segment)', 'Item::getAllItemByTagWeb/$1',['filter' => 'tenantFilter']);
    $routes->get('getfouritembycategoryweb', 'Item::getFourItemByCategoryWeb',['filter' => 'tenantFilter']);
    $routes->get('getfouritembytag/(:segment)', 'Item::getFourItemByTagWeb/$1',['filter' => 'tenantFilter']);
    $routes->post('viewweb', 'Item::show',['filter' => ['tenantFilter']]);

  });

  $routes->group('stock', function ($routes) {
    //Routes for stock
    $routes->get('getall', 'Stock::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Stock::getStocksPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Stock::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Stock::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Stock::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Stock::getStocksWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Stock::delete',['filter' => ['authFilter','tenantFilter']]); 
  });
    
  $routes->group('service', function ($routes) {
     //Routes for service
     $routes->get('getall', 'Service::index',['filter' => ['authFilter', 'tenantFilter']]);
     $routes->post('getallpaging', 'Service::getServicesPaging',['filter' => ['authFilter', 'tenantFilter']]); //add s
     $routes->get('view/(:segment)', 'Service::show/$1',['filter' => 'authFilter']);
     $routes->post('create', 'Service::create',['filter' => ['authFilter','tenantFilter']]);
     $routes->post('update', 'Service::update',['filter' => ['authFilter','tenantFilter']]);
     $routes->get('getallwebsite', 'Service::getServicesWebsite',['filter' => ['tenantFilter']]); //add s 
     $routes->post('delete', 'Service::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('gallery', function ($routes) {
    //Routes for gallery
    $routes->get('getall', 'Gallery::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Gallery::getGallerysPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Gallery::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Gallery::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Gallery::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Gallery::getGallerysWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Gallery::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('quotation', function ($routes) {
    //Routes for quotation
    $routes->get('getall', 'Quotation::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Quotation::getQuotationsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Quotation::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Quotation::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Quotation::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Quotation::getQuotationsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Quotation::delete',['filter' => ['authFilter','tenantFilter']]); 
  });

  $routes->group('po', function ($routes) {
    //Routes for Purchase Order (PO)
    $routes->get('getall', 'Po::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Po::getPosPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Po::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Po::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Po::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Po::getPosWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Po::delete',['filter' => ['authFilter','tenantFilter']]);
  });  
  
  $routes->group('sale', function ($routes) {
    //Routes for sale
    $routes->get('getall', 'Sale::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Sale::getSalesPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Sale::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Sale::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Sale::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Sale::getSalesWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Sale::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('team', function ($routes) {
    //Routes for Team
    $routes->get('getall', 'Team::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Team::getTeamsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Team::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Team::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Team::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Team::getTeamsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Team::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('blog', function ($routes) {
    //Routes for service
    $routes->get('getall', 'blog::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'blog::getBlogsPaging',['filter' => ['authFilter', 'tenantFilter']]); //add s
    $routes->get('view/(:segment)', 'Blog::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Blog::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Blog::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Blog::getBlogsWebsite',['filter' => ['tenantFilter']]); //add s 
    $routes->post('delete', 'Blog::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('staff', function ($routes) {
    //Routes for staff
    $routes->get('getall', 'Staff::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Staff::getStaffPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Staff::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Staff::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Staff::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Staff::getStaffsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Staff::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('vendor', function ($routes) {
    //Routes for vendor
    $routes->get('getall', 'Vendor::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Vendor::getVendorsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Vendor::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Vendor::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Vendor::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Vendor::getVendorsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Vendor::delete',['filter' => ['authFilter','tenantFilter']]);
  });

  $routes->group('purchase', function ($routes) {
    //Routes for Purchase
    $routes->get('getall', 'Purchase::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Purchase::getPurchasesPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Purchase::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Purchase::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Purchase::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Purchase::getPurchasesWebsite',['filter' => ['tenantFilter']]); // Get all purchase for website
    $routes->post('delete', 'Purchase::delete',['filter' => ['authFilter','tenantFilter']]);
     
  });

  $routes->group('lead', function ($routes) {
    //Routes for lead
    $routes->get('getall', 'Lead::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Lead::getLeadsPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->get('view/(:segment)', 'Lead::show/$1',['filter' => 'authFilter']);
    $routes->post('create', 'Lead::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Lead::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getallwebsite', 'Lead::getLeadsWebsite',['filter' => ['tenantFilter']]); // Get all Item for website
    $routes->post('delete', 'Lead::delete',['filter' => ['authFilter','tenantFilter']]); 
  });

  $routes->group('order', function ($routes) {
    //Routes for order
    $routes->get('getall', 'Order::index',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('getallpaging', 'Order::getOrdersPaging',['filter' => ['authFilter', 'tenantFilter']]);
    $routes->post('create', 'Order::create',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('update', 'Order::update',['filter' => ['authFilter','tenantFilter']]);
    $routes->post('delete', 'Order::delete',['filter' => ['authFilter','tenantFilter']]);
    $routes->get('getlastorder', 'Order::getLastOrder',['filter' => ['authFilter','tenantFilter']]);
  });

});