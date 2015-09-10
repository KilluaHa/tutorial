<?php
require_once('BaseController.php');
require_once('DiscussionController.php');
require_once(LIB_PATH . 'pusher/Pusher.php');
require_once(TU_LIB_PATH . 'queue/tutor_queue.php');

class PusherController extends BaseController
{   
    public function init()
    {
        parent::init();
        $this->authenticate();
    }
        
    public function pushAction()
    {        
        /*
        $app_id = '59571';
        $app_key = 'd632b6e7ebbc35f849c1';
        $app_secret = '3cdba6998de8eb777095';

        $pusher = new Pusher( $app_key, $app_secret, $app_id );
        $data  = array(
            'qid'               => 762,
            'processing_status' => ACTION_CLAIM,
            'processing_data'   => array(
                'uid'       => 8,
                'name'      => 'Vu Nguyen',
                'avatar'    => 'http://img1.tutorpl.us.s3.amazonaws.com/a/u0001k/8_45.jpg'
            )    
        );
        $pusher->trigger('private-student-7', 'questionClaimed', $data);
         * 
         */
        $pusher = new Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID);               
        //$pusher->trigger('presence-student-7', 'test', 'Hello World!');
        $pusher->trigger('presence-student-7', 'studentStartsDiscussion', array());
        exit('pushed');
    }
    
    public function studentendsdiscussionAction()
    {
        $pusher = new Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID);               
        //$pusher->trigger('presence-student-7', 'test', 'Hello World!');
        $pusher->trigger('presence-student-7', 'studentEndsDiscussion', array());
        exit('pushed');
    }
    
    /*
    public function authAction()
    {        
        $student = $_SESSION['student'];
        if ( ! $student){            
            header('HTTP/1.0 403 Forbidden');
            exit();
        }
        $channelName = $_POST['channel_name'];
        if ($channelName == 'presence-student-' . $student->uid){
            $pusher = new Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID);
            echo $pusher->presence_auth($channelName, $_POST['socket_id'], $student->uid, $student);                        
            exit();
        } 
        header('HTTP/1.0 403 Forbidden');
        exit();
    }
     * 
     */
    
    public function authAction()
    {   
        $prefix = 'presence-student-';
        $channelName = $_POST['channel_name'];        
        if (strpos($channelName, $prefix) !== FALSE){
            $u = new stdClass();
            $u->uid = $this->_user->uid;
            $u->first_name = $this->_user->first_name;
            $u->last_name = $this->_user->last_name;
            $u->avatar = $this->_user->avatar;
            $u->avatar_url = $this->_user->avatar_url;
            $pusher = new Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID);
            echo $pusher->presence_auth($channelName, $_POST['socket_id'], $this->_user->uid, $u);                        
            exit();
        } 
        $this->forbidden();
    }
    
    public function tutor_authAction()
    {
        $student = $_SESSION['student'];
        $tutor = $_SESSION['tutor'];
        if ( ! $student OR ! $tutor){            
            header('HTTP/1.0 403 Forbidden');
            exit();
        }
        $channelName = $_POST['channel_name'];
        if ($channelName == 'presence-student-' . $student->uid){
            $pusher = new Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID);
            echo $pusher->presence_auth($channelName, $_POST['socket_id'], $tutor->uid, $tutor);                        
            exit();
        } 
        $this->forbidden();
    }
    
    public function newmessageAction()
    {
        $student = $_SESSION['student'];
        $tutor = $_SESSION['tutor'];
        if ( ! $student OR ! $tutor){
            $this->_error('Action Prohibited');
        }       
        
        $qid = (int)$this->getPost('qid');
        if ( ! $qid){
            $this->_error('Question not found');
        }
        
        $body = 'Auto message created at ' . time();
        //add message to database
        $now = time();
        $qa = $this->getDb('qa');
        $mid = $qa->addDiscussionMessage(array(
            'qid'               => $qid,
            'from_uid'          => $this->_user->uid,
            'to_uid'            => $student->uid,
            'body'              => $body,
            'attachment_count'  => 0,
            'created'           => $now,
            'updated'           => $now
        ));        
                
        //increase question
        //discussion message count
        $qa->incrQuestionMessageCount($qid);
        
        //clear discussion cache
        $cachedDiscussion = new QDiscussionMessages(array(
            'qid' => $qid
        ));
        $cachedDiscussion->clear();
                
        $pusher = new Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID);
        $message = new stdClass();
        $message->mid = $mid;        
        $message->qid = $qid;
        $message->sender = $tutor;
        $message->receiver = $student;
        $message->body = $body;
        $message->attachment = NULL;
        $message->created = $now;
        
        $pusher->trigger('presence-student-' . $student->uid, 'newDiscussionMessage', $message);
        $this->_success(array(
            'body' => $body
        ));
    }   
    
    public function endsessionAction()
    {
        $student = $_SESSION['student'];
        $tutor = $_SESSION['tutor'];
        if ( ! $student OR ! $tutor){
            $this->_error('Action Prohibited');
        }       
        
        $qid = (int)$this->getPost('qid');
        if ( ! $qid){
            $this->_error('Question not found');
        }
                        
        $pusher = new Pusher(PUSHER_APP_KEY, PUSHER_APP_SECRET, PUSHER_APP_ID);
       
        $pusher->trigger('presence-student-' . $student->uid, 'discussionSessionEnded', array('qid' => $qid));
        $this->_success(array(
            'message' => 'Discussion session is ended by the tutor'
        ));
    }
    
    public function forbidden()
    {
        header('HTTP/1.0 403 Forbidden');
        exit();
    }
    
    public function getSessionTimeLeft($claimCreated)
    {
        return DiscussionController::SESSION_DURATION - (time() - $claimCreated);
    }
}