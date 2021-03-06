<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Internal logic for mod_visualclass
 *
 * @package    mod
 * @subpackage visualclass
 * @copyright  2013 Caltech Informática Ltda <class@class.com.br>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once(dirname(__FILE__) . '/lib.php');

/**
 * Session item
 *
 * This class represents a mod_visualclass session item.
 * A session item holds data about a single item in a
 * Visual Class course. It can be later used to record
 * detailed information about user's performance in
 * gradebook.
 *
 * @see mod_visualclass_session
 *
 * @package   mod_visualclass
 * @copyright Caltech Informática Ltda <class@class.com.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_visualclass_sessionitem {

    // Database Constants.

    const DB_TABLE = 'visualclass_item';

    // Item Types.

    const TYPE_TESTEVESTIBULAR = 1;
    const TYPE_PREENCHIMENTO = 2;
    const TYPE_ROTULOAVALIAVEL = 3;
    const TYPE_IMAGEMAVALIAVEL = 4;
    const TYPE_ARRASTARSOLTARIMAGEM = 5;
    const TYPE_ARRASTARSOLTAR = 6;
    const TYPE_ARRASTARDIFERENTESOLTAR = 7;
    const TYPE_GIRAFIGURAS = 8;
    const TYPE_LIGAPONTOS = 9;
    const TYPE_TESTE = 10;

    /**
     * Session item id
     *
     * @var int
     */
    private $_id;
    /**
     * Session item session id
     *
     * @var int
     */
    private $_sessionid;
    /**
     * Session item page title
     *
     * @var string
     */
    private $_pagetitle;
    /**
     * Session item type
     *
     * @var int
     */
    private $_type;
    /**
     * Session item question
     *
     * @var string
     */
    private $_question;
    /**
     * Session item answer correct
     *
     * @var mixed
     */
    private $_answercorrect;
    /**
     * Session item answer user
     *
     * @var string
     */
    private $_answeruser;

    /**
     * Constructor
     *
     * @param int $id
     * @param int $sessionid
     * @param string $pagetitle
     * @param int $type
     * @param string $question
     * @param mixed $answercorrect
     * @param string $answeruser
     */
    public function __construct(
        $id = null, $sessionid = null,
        $pagetitle = null, $type = null,
        $question = null, $answercorrect = null,
        $answeruser = null
    ) {
        $this->_id = $id;
        $this->_sessionid = $sessionid;
        $this->_pagetitle = $pagetitle;
        $this->_type = $type;
        $this->_question = $question;
        $this->_answercorrect = $answercorrect;
        $this->_answeruser = $answeruser;
    }

    /**
     * Writes/Updates session item into database
     *
     * @throws dml_exception
     */
    public function write() {
        global $DB;

        $data = new stdClass();
        $data->sessionid = $this->get_sessionid();
        $data->pagetitle = $this->get_pagetitle();
        $data->type = $this->get_type();
        $data->question = $this->get_question();

        // Handling various answers.
        $answercorrect = $this->get_answercorrect();
        if (is_array($answercorrect)) {
            $answercorrect = implode('|', $answercorrect);
        } else {
            if (is_object($answercorrect)) {
                $answercorrect = 'object';
            } else {
                $answercorrect = (string)$answercorrect;
            }
        }
        $data->answercorrect = $answercorrect;

        $data->answeruser = $this->get_answeruser();

        $id = $this->get_id();
        if (empty($id)) {
            $id = $DB->insert_record(self::DB_TABLE, $data);
            $this->set_id($id);
        } else {
            $data->id = $id;
            $DB->update_record(self::DB_TABLE, $data);
        }
    }

    /**
     * Reads session item from database
     *
     * @return bool
     * @throws dml_exception
     */
    public function read() {
        global $DB;

        $data = $DB->get_record(self::DB_TABLE, array('id' => $this->get_id()));
        if ($data instanceof stdClass) {
            $this->set_sessionid($data->sessionid);
            $this->set_pagetitle($data->pagetitle);
            $this->set_type($data->type);
            $this->set_question($data->question);

            // Handling various answers.
            $answercorrect = $data->answercorrect;
            if (strstr($answercorrect, '|') !== false) {
                $answercorrect = explode('|', $answercorrect);
            }
            $this->set_answercorrect($answercorrect);

            $this->set_answeruser($data->answeruser);
            return true;
        }
        return false;
    }

    /**
     * Deletes session item from database
     *
     * @return bool
     * @throws dml_exception
     */
    public function delete() {
        global $DB;

        return $DB->delete_records(
            self::DB_TABLE,
            array('id' => $this->get_id())
        );
    }

    /**
     * Checks if session item is correct
     *
     * @return bool
     */
    public function is_correct() {
        $user_input = $this->get_answeruser();

        // Blank input from user will always be considered an error, unless
        // it's a zero
        if ($user_input !== 0 && empty($user_input)) {
            return false;
        }

        // Both the correct answer and user input are parsed using 'strtolower'
        // function, unless exercise's type is fill
        $answer = $this->get_answercorrect();
        if ($this->get_type() != self::TYPE_PREENCHIMENTO) {
            $answer = strtolower($answer);
            $user_input = strtolower($user_input);
        }

        // There are 3 possible types for $answer: string, array or object
        if (is_string($answer) && strcmp($answer, $user_input) == 0) {
            return true;
        } else if (is_array($answer) && array_search($user_input, $answer) !== false) {
            return true;
        } else if (is_object($answer) && $answer == $user_input) {
            return true;
        }

        return false;
    }

    // Getters and Setters.

    /**
     * Get session item id
     *
     * @return int
     */
    public function get_id() {
        return $this->_id;
    }

    /**
     * Set session item id
     *
     * @param int $id
     */
    public function set_id($id) {
        $this->_id = $id;
    }

    /**
     * Get session id
     *
     * @return int
     */
    public function get_sessionid() {
        return $this->_sessionid;
    }

    /**
     * Set session id
     *
     * @param int $sessionid
     */
    public function set_sessionid($sessionid) {
        $this->_sessionid = $sessionid;
    }

    /**
     * Get page title
     *
     * @return string
     */
    public function get_pagetitle() {
        return $this->_pagetitle;
    }

    /**
     * Set page title
     *
     * @param string $pagetitle
     */
    public function set_pagetitle($pagetitle) {
        $this->_pagetitle = $pagetitle;
    }

    /**
     * Get type
     *
     * @return int
     */
    public function get_type() {
        return $this->_type;
    }

    /**
     * Set type
     *
     * @param int $type
     */
    public function set_type($type) {
        $this->_type = $type;
    }

    /**
     * Get question
     *
     * @return string
     */
    public function get_question() {
        return $this->_question;
    }

    /**
     * Set question
     *
     * @param string $question
     */
    public function set_question($question) {
        $this->_question = $question;
    }

    /**
     * Get answer correct
     *
     * @return mixed
     */
    public function get_answercorrect() {
        return $this->_answercorrect;
    }

    /**
     * Set answer correct
     *
     * @param mixed $answercorrect
     */
    public function set_answercorrect($answercorrect) {
        $this->_answercorrect = $answercorrect;
    }

    /**
     * Get answer user
     *
     * @return string
     */
    public function get_answeruser() {
        return $this->_answeruser;
    }

    /**
     * Set answer user
     *
     * @param string $answeruser
     */
    public function set_answeruser($answeruser) {
        $this->_answeruser = $answeruser;
    }

    /**
     * Get question type name
     *
     * @return string
     */
    public function get_type_name() {
        switch ($this->_type) {
            case self::TYPE_TESTEVESTIBULAR :
                return 'Teste Vestibular';
            case self::TYPE_PREENCHIMENTO :
                return 'Preenchimento Lacunas';
            case self::TYPE_ROTULOAVALIAVEL :
                return 'Rótulo Avaliável';
            case self::TYPE_IMAGEMAVALIAVEL :
                return 'Imagem Avaliável';
            case self::TYPE_ARRASTARSOLTARIMAGEM :
                return 'Arrastar/Soltar Imagem';
            case self::TYPE_ARRASTARSOLTAR :
                return 'Arrastar/Soltar';
            case self::TYPE_ARRASTARDIFERENTESOLTAR :
                return 'Arrastar≠Soltar';
            case self::TYPE_GIRAFIGURAS :
                return 'Gira Figuras';
            case self::TYPE_LIGAPONTOS :
                return 'Liga Pontos';
            case self::TYPE_TESTE :
                return 'Teste';
            default:
                return 'Exercício';
        }
    }

    /**
     * Get question answer name
     *
     * @return string
     */
    public function get_answeruser_name() {
        if ($this->get_type() == self::TYPE_TESTEVESTIBULAR) {
            switch ($this->get_answeruser()) {
                case 1 :
                    return 'a';
                case 2 :
                    return 'b';
                case 3 :
                    return 'c';
                case 4 :
                    return 'd';
                case 5 :
                    return 'e';
                default:
                    return get_string('noanswer', 'visualclass');
            }
        } else {
            if (is_array($this->_answeruser)) {
                return implode(get_string('report_separator', 'visualclass'), $this->_answeruser);
            }
        }
        return $this->get_answeruser();
    }

    /**
     * Get question answer name
     *
     * @return string
     */
    public function get_answercorrect_name() {
        if ($this->get_type() == self::TYPE_TESTEVESTIBULAR) {
            switch ($this->get_answercorrect()) {
                case 1 :
                    return 'a';
                case 2 :
                    return 'b';
                case 3 :
                    return 'c';
                case 4 :
                    return 'd';
                case 5 :
                    return 'e';
                default:
                    return get_string('noanswer', 'visualclass');
            }
        } else {
            if (is_array($this->_answercorrect)) {
                return implode(get_string('report_separator', 'visualclass'), $this->_answercorrect);
            }
        }
        return $this->get_answercorrect();
    }

    /**
     * Get is correct name
     *
     * @return string
     */
    public function is_correct_name() {
        return $this->is_correct() ? get_string('report_iscorrect', 'visualclass') : get_string('report_iswrong', 'visualclass');
    }
}

/**
 * Session
 *
 * This class represents a mod_visualclass activity session.
 * A session records the final score and controls the attempts.
 * It can be later used to record these informations in the
 * gradebook.
 *
 * @package   mod_visualclass
 * @copyright Caltech Informática Ltda <class@class.com.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_visualclass_session {

    // Database Constants.

    const DB_TABLE = 'visualclass_session';

    /**
     * Session id
     *
     * @var int
     */
    private $_id;
    /**
     * Session user id
     *
     * @var int
     */
    private $_userid;
    /**
     * Session mod id
     *
     * @var int
     */
    private $_modid;
    /**
     * Session attempt number
     *
     * @var int
     */
    private $_attemptnumber;
    /**
     * Session time start
     *
     * @var int
     */
    private $_timestart;
    /**
     * Session time stop
     *
     * @var int
     */
    private $_timestop;
    /**
     * Session total score
     *
     * @var number
     */
    private $_totalscore;
    /**
     * Session items
     *
     * @var array
     */
    private $_items;

    /**
     * Constructor
     *
     * @param int $id
     * @param int $userid
     * @param int $modid
     * @param int $attemptnumber
     * @param int $timestart
     * @param int $timestop
     * @param number $totalscore
     * @param array $items
     */
    public function __construct(
        $id = null, $userid = null, $modid = null,
        $attemptnumber = null, $timestart = null,
        $timestop = null, $totalscore = null,
        $items = null
    ) {
        $this->_id = $id;
        $this->_userid = $id;
        $this->_modid = $modid;
        $this->_attemptnumber = $attemptnumber;
        $this->_timestart = $timestart;
        $this->_timestop = $timestop;
        $this->_totalscore = $totalscore;
        $this->_items = $items;
    }

    /**
     * Writes/Updates session into database
     *
     * @throws dml_exception
     */
    public function write() {
        global $DB;

        $data = new stdClass();
        $data->userid = $this->get_userid();
        $data->modid = $this->get_modid();
        $data->attemptnumber = $this->get_attemptnumber();
        $data->timestart = $this->get_timestart();
        $data->timestop = $this->get_timestop();
        $data->totalscore = $this->get_totalscore();

        $id = $this->get_id();
        if (empty($id)) {
            $id = $DB->insert_record(self::DB_TABLE, $data);
            $this->set_id($id);
        } else {
            $data->id = $id;
            $DB->update_record(self::DB_TABLE, $data);
        }
    }

    /**
     * Reads session from database
     *
     * @return bool
     * @throws dml_exception
     */
    public function read() {
        global $DB;

        $data = $DB->get_record(self::DB_TABLE, array('id' => $this->get_id()));
        if ($data instanceof stdClass) {
            $this->set_userid($data->userid);
            $this->set_modid($data->modid);
            $this->set_attemptnumber($data->attemptnumber);
            $this->set_timestart($data->timestart);
            $this->set_timestop($data->timestop);
            $this->set_totalscore($data->totalscore);
            $items = $DB->get_records(
                mod_visualclass_sessionitem::DB_TABLE,
                array('sessionid' => $this->get_id()),
                'id DESC', 'id'
            );
            if (!empty($items)) {
                $itemsdata = array();
                foreach ($items as $item) {
                    $sessionitem = new mod_visualclass_sessionitem($item->id);
                    $sessionitem->read();
                    $itemsdata[] = $sessionitem;
                }
                $this->set_items($itemsdata);
            }
            return true;
        }
        return false;
    }

    /**
     * Deletes session from database
     *
     * @return bool
     * @throws dml_exception
     */
    public function delete() {
        global $DB;

        $sessionitems = $this->get_items();
        if (!empty($sessionitems)) {
            foreach ($sessionitems as $sessionitem) {
                if ($sessionitem instanceof mod_visualclass_sessionitem) {
                    $sessionitem->delete();
                }
            }
        }

        return $DB->delete_records(
            self::DB_TABLE,
            array('id' => $this->get_id())
        );
    }

    /**
     * Writes/Updates session totalscore into gradebook
     *
     * @param int $policy
     * @return void
     */
    public function write_totalscore($policy) {
        global $DB;

        $conditions = array(
            'userid' => $this->get_userid(),
            'modid' => $this->get_modid()
        );
        $sessions = $DB->get_records(self::DB_TABLE, $conditions);
        $score = $this->get_totalscore();
        switch ($policy) {
        case mod_visualclass_instance::GRADE_AVERAGE:
            $sessionsnumber = 0;
            foreach ($sessions as $session) {
                $sessionsnumber++;
                $score += $session->totalscore;
            }
            $score = $score / ($sessionsnumber > 0 ? $sessionsnumber : 1);
            break;
        case mod_visualclass_instance::GRADE_BEST:
            foreach ($sessions as $session) {
                if ($session->totalscore > $score) {
                    $score = $session->totalscore;
                }
            }
            break;
        case mod_visualclass_instance::GRADE_WORST:
            foreach ($sessions as $session) {
                if ($session->totalscore < $score) {
                    $score = $session->totalscore;
                }
            }
            break;
        case mod_visualclass_instance::GRADE_LAST:
            break;
        default:
        }

        $instance = $DB->get_record(
            mod_visualclass_instance::DB_TABLE,
            array('id' => $this->get_modid())
        );

        $gradeitem = new stdClass();
        $gradeitem->userid = $this->get_userid();
        $gradeitem->rawgrade = $score;
        $gradeitem->feedback = '';

        visualclass_grade_item_update($instance, $gradeitem);
    }

    // Getters and Setters.

    /**
     * Get session id
     *
     * @return int
     */
    public function get_id() {
        return $this->_id;
    }

    /**
     * Set session id
     *
     * @param int $id
     */
    public function set_id($id) {
        $this->_id = $id;
    }

    /**
     * Get user id
     *
     * @return int
     */
    public function get_userid() {
        return $this->_userid;
    }

    /**
     * Set user id
     *
     * @param int $userid
     */
    public function set_userid($userid) {
        $this->_userid = $userid;
    }

    /**
     * Get mod id
     *
     * @return int
     */
    public function get_modid() {
        return $this->_modid;
    }

    /**
     * Set mod id
     *
     * @param int $modid
     */
    public function set_modid($modid) {
        $this->_modid = $modid;
    }

    /**
     * Get attempt number
     *
     * @return int
     */
    public function get_attemptnumber() {
        return $this->_attemptnumber;
    }

    /**
     * Set attempt number
     *
     * @param int $attemptnumber
     */
    public function set_attemptnumber($attemptnumber) {
        $this->_attemptnumber = $attemptnumber;
    }

    /**
     * Get time start
     *
     * @return int
     */
    public function get_timestart() {
        return $this->_timestart;
    }

    /**
     * Set time start
     *
     * @param int $timestart
     */
    public function set_timestart($timestart) {
        $this->_timestart = $timestart;
    }

    /**
     * Get time stop
     *
     * @return int
     */
    public function get_timestop() {
        return $this->_timestop;
    }

    /**
     * Set time stop
     *
     * @param int $timestop
     */
    public function set_timestop($timestop) {
        $this->_timestop = $timestop;
    }

    /**
     * Get total score
     *
     * @return number
     */
    public function get_totalscore() {
        return $this->_totalscore;
    }

    /**
     * Set total score
     *
     * @param int $totalscore
     */
    public function set_totalscore($totalscore) {
        $this->_totalscore = $totalscore;
    }

    /**
     * Get session items
     *
     * @return array
     */
    public function get_items() {
        return $this->_items;
    }

    /**
     * Set session items
     *
     * @param array
     */
    public function set_items($items) {
        $this->_items = $items;
    }

    /**
     * Get the number of correct answers
     *
     * @return int
     */
    public function get_correct_answers() {
        return round(($this->get_totalscore() / 100) * count($this->get_items()));
    }

    /**
     * Get the number of wrong answers
     *
     * @return int
     */
    public function get_wrong_answers() {
        return count($this->get_items()) - $this->get_correct_answers();
    }

    /**
     * Get total time
     *
     * @return int
     */
    public function get_time() {
        return $this->get_timestop() - $this->get_timestart();
    }
}

/**
 * Instance
 *
 * This class represents a instance of mod_visualclass.
 * Each instance of mod_visualclass has a Visual Class
 * course.
 *
 * @package   mod_visualclass
 * @copyright Caltech Informática Ltda <class@class.com.br>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mod_visualclass_instance {

    // Attempts Constants.

    const ATTEMPT_MAX = 5;
    const ATTEMPT_UNLIMITED = 0;

    // Database Constants.

    const DB_TABLE = 'visualclass';

    // Grading Constants.

    const GRADE_AVERAGE = 1;
    const GRADE_BEST = 2;
    const GRADE_WORST = 3;
    const GRADE_LAST = 4;

    // Report Types.

    const REPORT_QUESTION = 'rquestion';
    const REPORT_USER = 'ruser';

    // Session.

    const SESSION_PREFIX = 'moodle_mod_visualclass_session_';

    // Time Constants.

    const TIME_BASE = 60;
    const TIME_FACTOR = 300;
    const TIME_MAX = 14400;
    const TIME_UNLIMITED = 6;

    // View Constants.

    const VIEW_MOODLE = 4;
    const VIEW_NEWTAB = 5;
    const VIEW_POPUP = 6;

    /**
     * Scripts
     *
     * @var array
     * @static
     */
    private static $scripts;

    /**
     * Instance id
     *
     * @var int
     */
    private $_id;
    /**
     * Instance course
     *
     * @var int
     */
    private $_course;
    /**
     * Instance name
     *
     * @var string
     */
    private $_name;
    /**
     * Instance project data
     *
     * @var string
     */
    private $_projectdata;
    /**
     * Instance project url
     *
     * @var string
     */
    private $_projecturl;
    /**
     * Instance project subject
     *
     * @var string
     */
    private $_projectsubject;
    /**
     * Instance policy attempts
     *
     * @var int
     */
    private $_policyattempts;
    /**
     * Instance policy time
     *
     * @var int
     */
    private $_policytime;
    /**
     * Instance policy grades
     *
     * @var int
     */
    private $_policygrades;
    /**
     * Instance policy view
     *
     * @var int
     */
    private $_policyview;
    /**
     * Popup width
     *
     * @var int
     */
    private $_policyview_width;
    /**
     * Popup height
     *
     * @var int
     */
    private $_policyview_height;
    /**
     * Instance sessions
     *
     * @var array
     */
    private $_sessions;
    /**
     * Hide grade
     *
     * @var int
     */
    private $_hidegrade;

    /**
     * Constructor
     *
     * @param int $id
     * @param int $course
     * @param string $name
     * @param string $projectdata
     * @param string $projecturl
     * @param string $projectsubject
     * @param int $policyattempts
     * @param int $policytime
     * @param int $policygrades
     * @param int $policyview
     * @param int $popupwidth
     * @param int $popupheight
     * @param array $sessions
     */
    public function __construct(
        $id = null, $course = null, $name = null,
        $projectdata = null, $projecturl = null, $projectsubject = null,
        $policyattempts = null, $policytime = null,
        $policygrades = null, $policyview = null,
        $popupwidth = null, $popupheight = null,
        $sessions = null, $hidegrade = null
    ) {
        $path = dirname(__FILE__);
        self::$scripts = array(
            'finaliza.html' => $path . '/scripts/finaliza.html',
            'finaliza.htm' => $path . '/scripts/finaliza.html',
            'moodle.js' => $path . '/scripts/moodle.js',
            'GeraHTML_json2.js' => $path . '/scripts/GeraHTML_json2.js',
            'logo.jpg' => $path . '/scripts/logo.jpg',
            'loading.gif' => $path . '/scripts/loading.gif',
            'status_error.png' => $path . '/scripts/status_error.png',
            'status_ok.png' => $path . '/scripts/status_ok.png'
        );

        $this->_id = $id;
        $this->_course = $course;
        $this->_name = $name;
        $this->_projectdata = $projectdata;
        $this->_projecturl = $projecturl;
        $this->_projectsubject = $projectsubject;
        $this->_policyattempts = $policyattempts;
        $this->_policytime = $policytime;
        $this->_policygrades = $policygrades;
        $this->_policyview = $policyview;
        $this->_policyview_width = $popupwidth;
        $this->_policyview_height = $popupheight;
        $this->_sessions = $sessions;
        $this->_hidegrade = $hidegrade;
    }

    /**
     * Writes/Updates instance into database
     *
     * @throws dml_exception
     */
    public function write() {
        global $DB;

        $data = new stdClass();
        $data->course = $this->get_course();
        $data->name = $this->get_name();
        $data->projectdata = $this->get_projectdata();
        $data->projecturl = $this->get_projecturl();
        $data->projectsubject = $this->get_projectsubject();
        $data->policyattempts = $this->get_policyattempts();
        $data->policytime = $this->get_policytime();
        $data->policygrades = $this->get_policygrades();
        $data->policyview = $this->get_policyview();
        $data->policyview_width = $this->get_policyview_width();
        $data->policyview_height = $this->get_policyview_height();
        $data->hidegrade = $this->get_hide_grade();

        $id = $this->get_id();
        if (empty($id)) {
            $id = $DB->insert_record(self::DB_TABLE, $data);
            $this->set_id($id);
        } else {
            $data->id = $id;
            $DB->update_record(self::DB_TABLE, $data);
        }
    }

    /**
     * Reads instance from database
     *
     * @return bool
     * @throws dml_exception
     */
    public function read() {
        global $DB;

        $data = $DB->get_record(self::DB_TABLE, array('id' => $this->get_id()));
        if ($data instanceof stdClass) {
            $this->set_course($data->course);
            $this->set_name($data->name);
            $this->set_projectdata($data->projectdata);
            $this->set_projecturl($data->projecturl);
            $this->set_projectsubject($data->projectsubject);
            $this->set_policyattempts($data->policyattempts);
            $this->set_policytime($data->policytime);
            $this->set_policygrades($data->policygrades);
            $this->set_policyview($data->policyview);
            $this->set_policyview_width($data->policyview_width);
            $this->set_policyview_height($data->policyview_height);
            $this->set_hide_grade($data->hidegrade);
            $sessionsitems = $DB->get_records(
                mod_visualclass_session::DB_TABLE,
                array('modid' => $this->get_id()),
                'id DESC', 'id'
            );
            if (!empty($sessionsitems)) {
                $sessions = array();
                foreach ($sessionsitems as $sessionitem) {
                    $session = new mod_visualclass_session($sessionitem->id);
                    $session->read();
                    $sessions[] = $session;
                }
                $this->set_sessions($sessions);
            }
            return true;
        }
        return false;
    }

    /**
     * Deletes instance from database
     *
     * @return bool
     * @throws dml_exception
     */
    public function delete() {
        global $DB;

        $sessions = $this->get_sessions();
        if (!empty($sessions)) {
            foreach ($sessions as $session) {
                if ($session instanceof mod_visualclass_session) {
                    $session->delete();
                }
            }
        }

        return $DB->delete_records(
            self::DB_TABLE,
            array('id' => $this->get_id())
        );
    }

    /**
     * Writes/Updates project data into filesystem
     *
     * @param int $course
     * @param int $draftitem
     * @return bool
     */
    public function write_projectdata($course, $draftitem) {
        global $CFG, $USER;

        $projectdata = $this->get_projectdata();
        if (empty($projectdata)) {
            $path = $CFG->dataroot . '/visualclass/' . $course . '/';
            if (!file_exists($path) && !mkdir($path, 0777, true)) {
                return false;
            }

            $context = context_user::instance($USER->id);
            $fs = get_file_storage();
            $files = $fs->get_area_files(
                $context->id, 'user', 'draft',
                $draftitem, 'id DESC', false
            );
            if (empty($files)) {
                return false;
            }

            $file = array_pop($files);
            $filecp = $path
                . date('YmdHis') . '_'
                . $file->get_filename();
            if (!$file->copy_content_to($filecp)) {
                return false;
            }

            $this->set_projectdata($filecp);
        } else {
            if (file_exists($projectdata)) {
                unlink($projectdata);
            }
            $this->set_projectdata(null);
            $this->write_projectdata($course, $draftitem);
        }
        return true;
    }

    /**
     * Deletes a project data from filesystem
     *
     * @return bool
     */
    public function delete_projectdata() {
        $projectdata = $this->get_projectdata();
        if (empty($projectdata)) {
            return false;
        } else {
            if (file_exists($projectdata)) {
                unlink($projectdata);
            }
        }

        $this->set_projectdata(null);
        return true;
    }

    /**
     * Writes/Updates a project url into filesystem
     *
     * @param int $course
     * @return bool
     */
    public function write_projecturl($course) {
        global $CFG;

        $projectdata = $this->get_projectdata();
        if (empty($projectdata)) {
            return false;
        }

        $projecturl = $this->get_projecturl();
        if (empty($projecturl)) {
            $path = str_replace($CFG->dataroot, $CFG->dirroot, $projectdata);
            $path = strstr($path, '.zip', true) . '/';
            if (!file_exists($path) && !mkdir($path, 0755, true)) {
                return false;
            }

            $archive = new ZipArchive();
            if ($archive->open($projectdata) !== true) {
                return false;
            }
            
            if ($archive->extractTo($path) !== true) {
                $archive->close();
                return false;
            }
            
            $archive->close();

            foreach (self::$scripts as $old => $new) {
                if (file_exists($path . $old)) {
                    unlink($path . $old);
                }
                copy($new, $path . $old);
            }

            // Changing source.js.

            $append = PHP_EOL . 'function chamaFinaliza() {' . PHP_EOL
                . '    link_click(\'finaliza.html\', null, null, null, null);' . PHP_EOL
                . '}' . PHP_EOL;
            file_put_contents($path . 'GeraHTML_source.js', $append, FILE_APPEND);

            $url = str_replace($CFG->dirroot, $CFG->wwwroot, $path);
            $this->set_projecturl($url);
        } else {
            $this->delete_projecturl();
            $this->write_projecturl($course);
        }
        return true;
    }

    /**
     * Deletes a project url from filesystem
     *
     * @return bool
     */
    public function delete_projecturl() {
        global $CFG;

        $projecturl = $this->get_projecturl();
        if (empty($projecturl)) {
            return false;
        } else {
            $path = str_replace($CFG->wwwroot, $CFG->dirroot, $projecturl);
            if (file_exists($path)) {
                $this->rrmdir($path);
            }

            $this->set_projecturl(null);
        }
        return true;
    }

    /**
     * Get the next attempt number for a user
     *
     * @param int $user
     * @return int
     */
    public function get_nextattemptnumber($user) {
        $sessions = $this->get_sessions();
        $lastattempt = 0;
        if (!empty($sessions)) {
            foreach ($sessions as $session) {
                if ($session->get_userid() === $user
                    && $session->get_attemptnumber() > $lastattempt
                ) {
                    $lastattempt = $session->get_attemptnumber();
                }
            }
        }
        return ++$lastattempt;
    }

    /**
     * Deletes a dir recursively
     *
     * @param string $dir
     * @return bool
     */
    public function rrmdir($dir) {
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (is_dir("$dir/$file")) ? $this->rrmdir("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }

    // Getters and Setters.

    /**
     * Get instance id
     *
     * @return int
     */
    public function get_id() {
        return $this->_id;
    }

    /**
     * Set instance id
     *
     * @param int $id
     */
    public function set_id($id) {
        $this->_id = $id;
    }

    /**
     * Get course
     *
     * @return int
     */
    public function get_course() {
        return $this->_course;
    }

    /**
     * Set course
     *
     * @param int $course
     */
    public function set_course($course) {
        $this->_course = $course;
    }

    /**
     * Get name
     *
     * @return string
     */
    public function get_name() {
        return $this->_name;
    }

    /**
     * Set name
     *
     * @param string $name
     */
    public function set_name($name) {
        $this->_name = $name;
    }

    /**
     * Get project data
     *
     * @return string
     */
    public function get_projectdata() {
        return $this->_projectdata;
    }

    /**
     * Set project data
     *
     * @param string $projectdata
     */
    public function set_projectdata($projectdata) {
        $this->_projectdata = $projectdata;
    }

    /**
     * Get project url
     *
     * @return string
     */
    public function get_projecturl() {
        return $this->_projecturl;
    }

    /**
     * Set project url
     *
     * @param string $projecturl
     */
    public function set_projecturl($projecturl) {
        $this->_projecturl = $projecturl;
    }

    /**
     * Get project subject
     *
     * @return string
     */
    public function get_projectsubject() {
        return $this->_projectsubject;
    }

    /**
     * Set project subject
     *
     * @param string $projectsubject
     */
    public function set_projectsubject($projectsubject) {
        $this->_projectsubject = $projectsubject;
    }

    /**
     * Get policy attempts
     *
     * @return int
     */
    public function get_policyattempts() {
        return $this->_policyattempts;
    }

    /**
     * Set policy attempts
     *
     * @param int $policyattempts
     */
    public function set_policyattempts($policyattempts) {
        $this->_policyattempts = $policyattempts;
    }

    /**
     * Get policy time
     *
     * @return int
     */
    public function get_policytime() {
        return $this->_policytime;
    }

    /**
     * Set policy time
     *
     * @param int $policytime
     */
    public function set_policytime($policytime) {
        $this->_policytime = $policytime;
    }

    /**
     * Get policy grades
     *
     * @return int
     */
    public function get_policygrades() {
        return $this->_policygrades;
    }

    /**
     * Set policy grades
     *
     * @param int $policygrades
     */
    public function set_policygrades($policygrades) {
        $this->_policygrades = $policygrades;
    }

    /**
     * Get policy view
     *
     * @return int
     */
    public function get_policyview() {
        return $this->_policyview;
    }

    /**
     * Set policy view
     *
     * @param int $policyview
     */
    public function set_policyview($policyview) {
        $this->_policyview = $policyview;
    }

    /**
     * Get policy view width
     *
     * @return int
     */
    public function get_policyview_width() {
        return $this->_policyview_width;
    }

    /**
     * Set policy view width
     *
     * @param int $popupwidth
     */
    public function set_policyview_width($popupwidth) {
        $this->_policyview_width = $popupwidth;
    }

    /**
     * Get policy view height
     *
     * @return int
     */
    public function get_policyview_height() {
        return $this->_policyview_height;
    }

    /**
     * Set policy view height
     *
     * @param int $popupheight
     */
    public function set_policyview_height($popupheight) {
        $this->_policyview_height = $popupheight;
    }

    /**
     * Get sessions
     *
     * @return array
     */
    public function get_sessions() {
        return $this->_sessions;
    }

    /**
     * Set sessions
     *
     * @param array $sessions
     */
    public function set_sessions($sessions) {
        $this->_sessions = $sessions;
    }

    /**
     * @return int
     */
    public function get_hide_grade() {
        return $this->_hidegrade;
    }

    /**
     * @param int $hidegrade
     */
    public function set_hide_grade($hidegrade) {
        $this->_hidegrade = $hidegrade;
    }
}
