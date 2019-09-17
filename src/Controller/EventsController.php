<?php
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\I18n\Date;
use Cake\I18n\Time;
use Cake\Mailer\Email;
use Cake\Http\Exception\ForbiddenException;
use Cake\Http\Exception\NotFoundException;
use Cake\ORM\TableRegistry;

class EventsController extends AppController
{
    
    public function beforeFilter(Event $event) {
        
        parent::beforeFilter($event);
        $this->Event = TableRegistry::getTableLocator()->get('Events');
        $this->AppAuth->allow([
            'detail',
            'all',
            'ajaxGetAllEventsForMap',
            'feed'
        ]);
    }
    
    public function isAuthorized($user)
    {
        
        if ($this->request->getParam('action') == 'myEvents') {
            return $this->AppAuth->user();
        }
        
        if ($this->request->getParam('action') == 'add') {
            
            if ($this->AppAuth->isAdmin()) {
                $this->useDefaultValidation = false;
                return true;
            }
            
            // repair helpers are not allowed to add events
            if (!$this->AppAuth->isOrga()) {
                return false;
            }
            
            $workshopUid = (int) $this->request->getParam('pass')[0];
            $this->Workshop = TableRegistry::getTableLocator()->get('Workshops');
            $workshop = $this->Workshop->getWorkshopForIsUserInOrgaTeamCheck($workshopUid);
            if ($this->Workshop->isUserInOrgaTeam($this->AppAuth->user(), $workshop)) {
                return true;
            }
            
        }
        
        if (in_array($this->request->getParam('action'), ['edit', 'delete', 'duplicate'])) {
            
            // repair helpers are not allowed to edit, delete or duplicate events (even not own content - which does not exist because "add" is locked for repairhelpers too)
            if (!($this->AppAuth->isOrga() || $this->AppAuth->isAdmin())) {
                return false;
            }
            
            $eventUid = (int) $this->request->getParam('pass')[0];
            
            $this->Event = TableRegistry::getTableLocator()->get('Events');
            $event = $this->Event->find('all', [
                'conditions' => [
                    'Events.uid' => $eventUid,
                    'Events.status > ' . APP_DELETED
                ]
            ])->first();
            $workshopUid = $event->workshop_uid;
            
            if ($this->request->getParam('action') == 'edit' && $event->datumstart->isPast()) {
                return false;
            }
            
            if ($this->AppAuth->isAdmin()) {
                $this->useDefaultValidation = false;
                return true;
            }
            
            // all approved orgas are allowed to edit their events
            $this->Workshop = TableRegistry::getTableLocator()->get('Workshops');
            $workshop = $this->Workshop->getWorkshopForIsUserInOrgaTeamCheck($workshopUid);
            if ($this->Workshop->isUserInOrgaTeam($this->AppAuth->user(), $workshop)) {
                return true;
            }
            
            return false;
        }
        
        return parent::isAuthorized($user);
        
    }
    
    public function myEvents()
    {
        
        $hasEditEventPermissions = $this->AppAuth->isAdmin() || $this->AppAuth->isOrga();
        
        $this->Workshop = TableRegistry::getTableLocator()->get('Workshops');
        // complicated is-user-orga-check no needed again because this page is only accessible for orga users
        if ($this->AppAuth->isAdmin()) {
            $workshops = $this->Workshop->getWorkshopsForAdmin(APP_DELETED);
        } else {
            $workshops = $this->Workshop->getWorkshopsForAssociatedUser($this->AppAuth->getUserUid(), APP_DELETED);
        }
        
        $workshops->contain([
            'Events.InfoSheets.OwnerUsers',
            'Events.InfoSheets.Brands' => function($q) {
                return $q->select($this->Workshop->Events->InfoSheets->Brands);
            },
            'Events.InfoSheets.Categories' => function($q) {
                return $q->select($this->Workshop->Events->InfoSheets->Categories);
            }
        ]);
        
        $this->Workshop->getAssociation('Events')->setConditions(['Events.status > ' . APP_DELETED])
        ->setSort([
            'Events.datumstart' => 'DESC',
            'Events.uhrzeitstart' => 'DESC'
        ]);
        
        $conditions = [
            'InfoSheets.status > ' . APP_DELETED
        ];
        
        // show only own content for repair helper
        if (!$hasEditEventPermissions) {
            $conditions['InfoSheets.owner'] = $this->AppAuth->getUserUid();
        }
        
        $this->Workshop->getAssociation('Events')->getAssociation('InfoSheets')
        ->setConditions($conditions)
        ->setSort([
            'InfoSheets.device_name' => 'ASC'
        ]);
        
        foreach($workshops as $workshop) {
            $workshop->infoSheetCount = 0;
            if (!empty($workshop->events)) {
                foreach($workshop->events as $event) {
                    $workshop->infoSheetCount += count($event->info_sheets);
                }
            }
        }
        
        $this->set('workshops', $workshops);
        
        $metaTags = [
            'title' => 'Meine Termine'
        ];
        $this->set('metaTags', $metaTags);
        
        $this->set('hasEditEventPermissions', $hasEditEventPermissions);
        $this->set('infoSheetColspan', $hasEditEventPermissions ? 11 : 8);
        
    }
    
    public function feed()
    {
        
        if (! $this->RequestHandler->prefers('rss')) {
            throw new NotFoundException('kein rss');
        }
        
        $this->Event = TableRegistry::getTableLocator()->get('Events');
        $events = $this->Event->find('all', [
            'conditions' => $this->Event->getListConditions(),
            'order' => [
                'Events.datumstart' => 'ASC'
            ],
            'contain' => [
                'Workshops'
            ]
        ]);
        
        if ($events->count() == 0) {
            throw new NotFoundException('no events found');
        }
        
        $this->set('events', $events);
        
    }
    
    public function delete($eventUid)
    {
        if ($eventUid === null) {
            throw new NotFoundException;
        }
        
        $event = $this->Event->find('all', [
            'conditions' => [
                'Events.uid' => $eventUid,
                'Events.status >= ' . APP_DELETED
            ],
            'contain' => [
                'Categories',
                'Workshops'
            ]
        ])->first();
        
        if (empty($event)) {
            throw new NotFoundException;
        }
        
        // keep this line here!!!
        $originalEventStatus = $event->status;
        
        $patchedEntity = $this->Event->patchEntity(
            $this->Event->get($eventUid),
            ['status' => APP_DELETED]
        );
        
        if ($this->Event->save($patchedEntity)) {
            $this->AppFlash->setFlashMessage('Der Termin wurde erfolgreich gelöscht.');
            
            if ($originalEventStatus) {
                // START notify subscribers
                $this->Worknews = TableRegistry::getTableLocator()->get('Worknews');
                $subscribers = $this->Worknews->find('all', [
                    'conditions' => [
                        'Worknews.workshop_uid' => $event->workshop_uid,
                        'Worknews.confirm' => 'ok'
                    ]
                ]);
                
                if (!empty($subscribers)) {
                    $email = new Email('default');
                    $email->viewBuilder()->setTemplate('event_deleted');
                    foreach ($subscribers as $subscriber) {
                        $email->setTo($subscriber->email)
                        ->setSubject('Termin gelöscht')
                        ->setViewVars([
                            'domain' => Configure::read('App.fullBaseUrl'),
                            'url' => Configure::read('AppConfig.htmlHelper')->urlWorkshopDetail($event->workshop->url),
                            'unsub' => $subscriber->unsub
                        ]);
                        $email->send();
                    }
                }
                // END notify subscribers
            }
            
        } else {
            $this->AppFlash->setErrorMessage('Beim Löschen ist ein Fehler aufgetreten');
        }
        
        $this->redirect($this->request->referer());
        
    }
    
    public function add($preselectedWorkshopUid)
    {
        
        if ($preselectedWorkshopUid === null) {
            throw new NotFoundException;
        }
        
        $event = $this->Event->newEntity(
            [
                'status' => APP_ON,
                'workshop_uid' => $preselectedWorkshopUid,
                'datumstart' => Date::now(),
                'uhrzeitstart' => new Time('00:00'),
                'uhrzeitend' => new Time('00:00')
            ],
            ['validate' => false]
        );
        $this->set('metaTags', ['title' => 'Termin erstellen']);
        
        $this->Workshop = TableRegistry::getTableLocator()->get('Workshops');
        // complicated is-user-orga-check no needed again because this page is only accessible for orga users
        if ($this->AppAuth->isAdmin()) {
            $workshops = $this->Workshop->getWorkshopsForAdmin(APP_DELETED);
        } else {
            $workshops = $this->Workshop->getWorkshopsForAssociatedUser($this->AppAuth->getUserUid(), APP_DELETED);
        }
        
        $this->set('workshopsForDropdown', $this->Workshop->transformForDropdown($workshops));
        $this->set('preselectedWorkshopUid', $preselectedWorkshopUid);
        $this->set('editFormUrl', Configure::read('AppConfig.htmlHelper')->urlEventNew($preselectedWorkshopUid));
        
        $this->_edit($event, false);
        
        // assures rendering of success message on redirected page and NOT before and then not showing it
        if (empty($this->request->getData())) {
            $this->render('edit');
        }
    }
    
    public function duplicate($eventUid) {
        $event = $this->Event->find('all', [
            'conditions' => [
                'Events.uid' => $eventUid,
                'Events.status >= ' . APP_DELETED
            ],
            'contain' => [
                'Categories',
                'Workshops'
            ]
        ])->first();
        
        if (empty($event)) {
            throw new NotFoundException;
        }
        $this->setIsCurrentlyUpdated($event->uid);
        $this->set('metaTags', ['title' => 'Termin duplizieren']);
        $this->set('editFormUrl', Configure::read('AppConfig.htmlHelper')->urlEventNew($event->workshop_uid));
        $this->_edit($event, false);
        $this->render('edit');
    }
    
    public function edit($eventUid)
    {
        
        if ($eventUid === null) {
            throw new NotFoundException;
        }
        
        $event = $this->Event->find('all', [
            'conditions' => [
                'Events.uid' => $eventUid,
                'Events.status >= ' . APP_DELETED
            ],
            'contain' => [
                'Categories',
                'Workshops'
            ]
        ])->first();
        
        if (empty($event)) {
            throw new NotFoundException;
        }
        
        $this->setIsCurrentlyUpdated($event->uid);
        $this->set('metaTags', ['title' => 'Termin bearbeiten']);
        $this->set('editFormUrl', Configure::read('AppConfig.htmlHelper')->urlEventEdit($event->uid));
        $this->_edit($event, true);
    }
    
    private function _edit($event, $isEditMode)
    {
        $this->Category = TableRegistry::getTableLocator()->get('Categories');
        $this->set('categories', $this->Category->getForDropdown(APP_ON));
        
        $this->set('uid', $event->uid);
        
        $this->setReferer();
        
        if (!empty($this->request->getData())) {
            
            if (!$this->request->getData('Events.use_custom_coordinates')) {
                $addressString = $this->request->getData('Events.strasse') . ', ' . $this->request->getData('Events.zip') . ' ' . $this->request->getData('Events.ort') . ', ' . $this->request->getData('Events.country');
                $coordinates = $this->getLatLngFromGeoCodingService($addressString);
                $this->request = $this->request->withData('Events.lat', $coordinates['lat']);
                $this->request = $this->request->withData('Events.lng', $coordinates['lng']);
            }
            if ($this->request->getData('Events.use_custom_coordinates')) {
                $this->request = $this->request->withData('Events.lat', str_replace(',', '.', $this->request->getData('Events.lat')));
                $this->request = $this->request->withData('Events.lng', str_replace(',', '.', $this->request->getData('Events.lng')));
            }
            
            if ($this->request->getData('Events.datumstart')) {
                $this->request = $this->request->withData('Events.datumstart', new Time($this->request->getData('Events.datumstart')));
            }
            
            $patchedEntity = $this->Event->getPatchedEntityForAdminEdit($event, $this->request->getData(), $this->useDefaultValidation);
            
            // keep this line here!!!
            $sendNotificationMails = $patchedEntity->isDirty('status') && $patchedEntity->status;
            
            $errors = $patchedEntity->getErrors();
            
            if (isset($errors['lat']) && isset($errors['lat']['numeric'])) {
                $this->AppFlash->setFlashError($errors['lat']['numeric']);
            }
            
            if (empty($errors)) {
                
                $patchedEntity = $this->patchEntityWithCurrentlyUpdatedFields($patchedEntity);
                $entity = $this->stripTagsFromFields($patchedEntity, 'Event');
                
                if ($this->Event->save($entity)) {
                    
                    $this->AppFlash->setFlashMessage($this->Event->name_de . ' erfolgreich gespeichert.');
                    
                    // no workshop set in add mode
                    // never send notification mail on add! @see SendWorknewsNotificationShell
                    // if event is edited and renotify is active, do send mail
                    if (!empty($patchedEntity->workshop)) {
                        $workshop = $patchedEntity->workshop;
                        $sendNotificationMails |= $patchedEntity->renotify;
                    }
                    
                    // START notify subscribers
                    if (isset($workshop) && $sendNotificationMails) {
                        $this->Worknews = TableRegistry::getTableLocator()->get('Worknews');
                        $subscribers = $this->Worknews->getSubscribers($patchedEntity->workshop_uid);
                        if (!empty($subscribers)) {
                            $this->Worknews->sendNotifications($subscribers, 'Termin geändert: ' . $workshop->name, 'event_changed', $workshop, $patchedEntity);
                        }
                    }
                    // END notify subscribers
                    
                    $this->redirect($this->request->getData()['referer']);
                    
                } else {
                    $this->AppFlash->setFlashError($this->Event->name_de . ' <b>nicht</b>erfolgreich gespeichert.');
                }
                                
            } else {
                $event = $patchedEntity;
            }
        }
        
        $this->set('event', $event);
        $this->set('isEditMode', $isEditMode);
        
        if (!empty($errors)) {
            $this->render('edit');
        }
        
    }
    
    public function ajaxGetAllEventsForMap()
    {
        
        if (!$this->request->is('ajax')) {
            throw new ForbiddenException();
        }
        
        $this->RequestHandler->renderAs($this, 'json');
        
        $keyword = '';
        $conditions = $this->Event->getListConditions();
        
        $allParamsEmpty = empty($this->request->getQuery('keyword'));
        
        $events = $this->Event->find('all', [
            'conditions' => $conditions,
            'fields' => $this->Event->getListFields(),
            'order' => $this->Event->getListOrder(),
            'contain' => [
                'Workshops',
                'Categories'
            ]
        ])->distinct(['Events.uid']);
        
        if (!empty($this->request->getQuery('keyword'))) {
            $keyword = strtolower(trim($this->request->getQuery('keyword')));
            if ($keyword !== '' && $keyword !== 'null') {
                $events->where($this->Event->getKeywordSearchConditions($keyword, false));
            }
        }
        
        if (! $allParamsEmpty) {
            $events->where($this->Event->getKeywordSearchConditions($keyword, true));
        }
        
        if (!empty($this->request->getQuery('categories'))) {
            $categories = explode(',', $this->request->getQuery('categories'));
            if (!empty($categories)) {
                $events->notMatching('Categories', function(\Cake\ORM\Query $q) use ($categories) {
                    return $q->where([
                        'Categories.id IN' => $categories
                    ]);
                });
            }
        }
        $this->set('data', [
            'status' => 1,
            'message' => 'ok',
            'events' => $this->combineEventsForMap($events)
        ]);
        $this->set('_serialize', 'data');
    }
    
    function all()
    {
        
        $metaTags = [
            'title' => 'Suche Reparaturtermine in deiner Nähe',
            'description' => 'Termine und Veranstaltungen von Repair Cafés und anderen ' . Configure::read('AppConfig.initiativeNamePlural') . ' in deiner Nähe',
            'keywords' => 'repair café, repair cafe, reparieren, repair, reparatur, reparatur-initiativen, netzwerk reparatur-initiativen, reparaturtermin, reparaturveranstaltung'
        ];
        $this->set('metaTags', $metaTags);
        
        $conditions = $this->Event->getListConditions();
        
        $selectedCategories = !empty($this->request->getQuery('categories')) ? explode(',', $this->request->getQuery('categories')) : [];
        $this->set('selectedCategories', $selectedCategories);
        
        $this->Category = TableRegistry::getTableLocator()->get('Categories');
        $categories = $this->Category->getMainCategoriesForFrontend();
        
        $preparedCategories = [];
        foreach ($categories as $category) {
            // category is selected
            if (count($selectedCategories) > 0) {
                if (in_array($category->id, $selectedCategories)) {
                    $categoryClass = 'selected';
                    $categoryIdsForNewUrl = [];
                    foreach ($selectedCategories as $sc) {
                        if ($sc != $category->id) {
                            $categoryIdsForNewUrl[] = $sc;
                        }
                    }
                } else {
                    // category is not selected
                    $categoryClass = 'not-selected';
                    $categoryIdsForNewUrl = array_merge($selectedCategories, [
                        $category->id
                    ]);
                }
            }
            
            // initially all categories selected
            if (count($selectedCategories) == 0) {
                $categoryClass = 'selected';
                $categoryIdsForNewUrl = array_merge($selectedCategories, [
                    $category->id
                ]);
            }
            
            
            $newUrl = 'categories=' . join(',', $categoryIdsForNewUrl);
            $newUrl = str_replace('categories=,', '&categories=', $newUrl);
            
            if (empty($this->request->getQuery('keyword'))) {
                $newUrl = '?' . $newUrl;
            } else {
                $newUrl = '?keyword=' . $this->request->getQuery('keyword') . '&' . $newUrl;
            }
            
            $newUrl = str_replace('//', '/', $newUrl);
            
            $category['href'] = $newUrl;
            $category['class'] = $categoryClass;
            $preparedCategories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'icon' => $category->icon,
                'href' => $newUrl,
                'class' => $categoryClass
            ];
        }
        $this->set('preparedCategories', $preparedCategories);
        
        $query = $this->Events->find('all', [
            'conditions' => $conditions,
        ])->distinct(['Events.uid']);
        
        $keyword = '';
        if (!empty($this->request->getQuery('keyword'))) {
            $keyword = strtolower(trim($this->request->getQuery('keyword')));
            $query->where($this->Event->getKeywordSearchConditions($keyword, false));
        }
        $this->set('keyword', $keyword);
        
        $resetCategoriesUrl = '/reparatur-termine';
        if ($keyword != '') {
            $resetCategoriesUrl = '/reparatur-termine?keyword=' . $keyword;
        }
        $this->set('resetCategoriesUrl', $resetCategoriesUrl);
        
        if (!empty($this->request->getQuery('categories'))) {
            $categories = explode(',', $this->request->getQuery('categories'));
            if (!empty($categories)) {
                $query->matching('Categories', function(\Cake\ORM\Query $q) use ($categories) {
                    return $q->where([
                        'Categories.id IN' => $categories
                    ]);
                });
            }
        }
        $events = $this->paginate($query, [
            'fields' => $this->Event->getListFields(),
            'order' => $this->Event->getListOrder(),
            'contain' => [
                'Workshops',
                'Categories'
            ]
        ]);
        $this->set('events', $events);
        
        // $events needs to be cloned, because unset($e['workshop']); in combineEventsForMap would also remove it from $events
        // $events cannot be cloned because it is a resultset
        // so call $this->pagniate twice - no performance problem!
        $newEvents = $this->paginate($query, [
            'fields' => $this->Event->getListFields(),
            'order' => $this->Event->getListOrder(),
            'contain' => [
                'Workshops'
            ]
        ]);
        $eventsForMap = $this->combineEventsForMap($newEvents);
        $this->set('eventsForMap', $eventsForMap);
        
        $urlOptions = [
            'url' => [
                'controller' => 'reparatur-termine',
                'keyword' => $keyword
            ]
        ];
        $this->set('urlOptions', $urlOptions);
        
    }
    
    /**
     * combines multiple events to one marker
     *
     * @param array $events
     * @return array
     */
    private function combineEventsForMap($events)
    {
        $eventsForMap1 = [];
        
        foreach ($events as $event) {
            $preparedWorkshop = [];
            if ($event->workshop) {
                $tmpWorkshop = $event->workshop;
                $preparedWorkshop['name'] = $tmpWorkshop->name;
                $preparedWorkshop['image'] = $tmpWorkshop->image;
                $preparedWorkshop['url'] = $tmpWorkshop->url;
            }
            $eventsForMap1[$event->uniquePlace]['Event'] = $event;
            $eventsForMap1[$event->uniquePlace]['Events'][] = $event;
            $eventsForMap1[$event->uniquePlace]['Workshop'] = $preparedWorkshop;
        }
        $eventsForMap = [];
        foreach ($eventsForMap1 as $event) {
            $preparedEvent = [
                'Event' => $event['Event'],
                'Workshop' => $event['Workshop'],
                'Events' => []
            ];
            foreach ($event['Events'] as $e) {
                unset($e['workshop']);
                $preparedEvent['Events'][] = $e;
            }
            $eventsForMap[] = $preparedEvent;
        }
        return $eventsForMap;
    }
    
}
?>