<?php
namespace Admin\Controller;

use App\Controller\Component\StringComponent;
use Cake\Event\Event;
use Cake\ORM\TableRegistry;

class UsersController extends AdminAppController
{

    public $searchName = false;

    public $searchText = false;

    public function __construct($request = null, $response = null)
    {
        parent::__construct($request, $response);
        $this->User = TableRegistry::getTableLocator()->get('Users');
        $this->Workshop = TableRegistry::getTableLocator()->get('Workshops');
        $this->Country = TableRegistry::getTableLocator()->get('Countries');
        $this->Group = TableRegistry::getTableLocator()->get('Groups');
    }

    public function beforeFilter(Event $event)
    {
        parent::beforeFilter($event);
        
        $this->addSearchOptions([
            'Users.firstname' => [
                'name' => 'Users.firstname',
                'searchType' => 'search'
            ],
            'Users.lastname' => [
                'name' => 'Users.lastname',
                'searchType' => 'search'
            ],
            'Users.email' => [
                'name' => 'Users.email',
                'searchType' => 'search'
            ],
            'UsersGroups.group_id' => [
                'name' => 'UsersGroups.group_id',
                'association' => 'Groups',
                'searchType' => 'matching',
                'extraDropdown' => true
            ],
            'UsersWorkshops.workshop_uid' => [
                'name' => 'UsersWorkshop.workshop_uid',
                'association' => 'Workshops',
                'searchType' => 'matching',
                'extraDropdown' => true
            ]
        ]);
        
        // für optional groups dropdown
        $this->generateSearchConditions('opt-1');
        $this->generateSearchConditions('opt-2');
    }

    public function index()
    {
        parent::index();
        
        $conditions = [
            'Users.status > ' . APP_DELETED
        ];
        $conditions = array_merge($this->conditions, $conditions);
        
        $query = $this->User->find('all', [
            'conditions' => $conditions,
            'contain' => [
                'OwnerUsers',
                'Groups',
                'Workshops' => [
                    'fields' => [
                        'UsersWorkshops.user_uid'
                    ]
                ],
                'OwnerWorkshops'
            ]
        ]);
        
        $query = $this->addMatchingsToQuery($query);
        
        $objects = $this->paginate($query, [
            'order' => [
                'Users.created' => 'DESC'
            ]
        ]);
        $this->set('objects', $objects->toArray());
        
        $this->Workshop = TableRegistry::getTableLocator()->get('Workshops');
        $this->set('workshops', $this->Workshop->getForDropdown());
    }
}
?>