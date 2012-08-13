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
 * Renderer for outputting parts of a question belonging to the legacy
 * adaptive behaviour.
 *
 * @package    qbehaviour
 * @subpackage adaptive
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/question/behaviour/adaptiveweighted/renderer.php');

/**
 * Renderer for outputting parts of a question belonging to the legacy
 * adaptive behaviour.
 *
 * @copyright  2009 The Open University
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qbehaviour_adaptiveweightedhints_renderer extends qbehaviour_adaptiveweighted_renderer {

    /**
     * Several behaviours need a submit button, so put the common code here.
     * The button is disabled if the question is displayed read-only.
     * @param question_display_options $options controls what should and should not be displayed.
     * @return string HTML fragment.
     */
    protected function submit_button(question_attempt $qa, question_display_options $options, $button_name='submit', $button_text=null, $class='', $disabled=false) {

        //if no button text was provided, then use the default string
        if($button_text === null) {
            $button_text = get_string('gradenow', 'qbehaviour_adaptiveweighted');
        }


        //compute the button's attributes
        $attributes = array
        (
            'type' => 'submit',
            'id' => $qa->get_behaviour_field_name($button_name),
            'name' => $qa->get_behaviour_field_name($button_name),
            'value' => $button_text,
            'alt' => $button_text,
            'class' => 'submit btn '.$class,
        );

        //if the question is read-only, prevent the button from being clicked
        if ($options->readonly || $disabled) {
            $attributes['disabled'] = 'disabled';
        }

        //and add the appropirate colorization
        if ($disabled) {
            $attributes['class'] .= ' disabled';
        }

        //generate a new submit button 
        $output = html_writer::empty_tag('input', $attributes);

        //if this question isn't read-only, initialize the submit button routine, which prevents multiple submissions
        if (!$options->readonly) 
            $this->page->requires->js_init_call('M.core_question_engine.init_submit_button', array($attributes['id'], $qa->get_slot()));

        //finally, return the rendered submit button
        return $output;
    }

    /**
     * Render the question's controls.
     */
    public function controls(question_attempt $qa, question_display_options $options) {

        //though these aren't strictly controls, this is the best place to render these I've found so far
        $output = $this->get_hints($qa);

        //display the submit and save button
        $output .= $this->submit_button($qa, $options, 'submit', get_string('gradenow', 'qbehaviour_adaptiveweighted'), 'gradenow');
        $output .= $this->submit_button($qa, $options, 'save', get_string('savenow', 'qbehaviour_adaptiveweighted'), 'savenow');

        //get the number of available hints
        $hints_available = count($qa->get_question()->hints);

        //if the question has hints, display the "get hints" button
        if($hints_available) {
            //get the number of hints used
            $hints_used = $qa->get_last_behaviour_var('_hints', 0);

            //if there aren't any hints left, disable the hint button
            $disabled = $hints_used >= $hints_available;

            //if the button is disabled, display "no more hints"
            if($disabled)
                $label = get_string('nomorehints', 'qbehaviour_adaptiveweightedhints');
            //if the user has already gotten a hint, display an "additional" message
            elseif($hints_used)
                $label = get_string('getadditionalhint', 'qbehaviour_adaptiveweightedhints');
            //otherwise, display "Get a hint"
            else
                $label = get_string('gethintnow', 'qbehaviour_adaptiveweightedhints');


            $output .= $this->submit_button($qa, $options, 'hint', $label, 'gethint', $disabled);
        }

        return $output;
    }

    /**
     * Get all of the _relevant_ hints for a given Question Attempt.
     * 
     * @param question_attempt $qa  The Question Attempt for which the hints are being requested.
     * @return string               The hints, as a collection of HTML code to be added to the question. 
     */
    protected function get_hints(question_attempt $qa) {

        //get a reference to the question object;
        $question = $qa->get_question();

        //get the number of requested hints
        $hint_count = $qa->get_last_behaviour_var('_hints', 0);

        //and get the number of _available_ hints
        $available_hints = count($question->hints);

        //start an output buffer
        $output = '';

        //get as many hints as the user has requested (or less, if less are available; e.g. if the question is editied)
        for($i = 0; $i < min($hint_count, $available_hints); ++$i) {

            //get the hint object
            $hint = $question->get_hint($i, $qa);

            //generate the text for the inside of the div
            $hint_text = html_writer::tag('h3', get_string('hint', 'qbehaviour_adaptiveweightedhints')).html_writer::tag('div', $hint->hint, array('class' => 'hinttext'));

            //and add the hint to the feedback class
            $output .= html_writer::tag('div', $hint_text, array('class' => 'hint'));
        }

        //return the buffered output
        return $output;
    }

	/**
	* Display the information about the penalty calculations.
	* @param question_attempt $qa the question attempt.
	* @param object $mark contains information about the current mark.
	* @param question_display_options $options display options.
	*/
    protected function penalty_info(question_attempt $qa, $mark, question_display_options $options) {


		//if no penalties have been set, return an empty string
        if (!$qa->get_question()->penalty) {
            return '';
        }
		
		$output = '';
		
		// Print details of grade adjustment due to penalties
		if ($mark->raw != $mark->cur)
			$output .= ' ' . get_string('gradingdetailsadjustment', 'qbehaviour_adaptive', $mark);
		
	
		// Print information about any new penalty, only relevant if the answer can be improved.
		if ($qa->get_behaviour()->is_state_improvable($qa->get_state())) 
		{
			//calculate the maximum score the student can still achieve
			$maxpossible = $mark->max - $mark->max * $qa->get_last_behaviour_var('_sumpenalty', 0);
			
			$lastpenalty = $mark->max * $qa->get_last_behaviour_var('_lastpenalty', 0);
			
			//and return that, instead of penalty information
			if($maxpossible > 0) {
				$output .= ' ' . get_string('gradingdetailsmaxpossible', 'qbehaviour_adaptiveweighted', array('lastpenalty' => format_float($lastpenalty, $options->markdp), 'maxpossible' => format_float(max($maxpossible, 0), $options->markdp), 'max' => $mark->max));
			} else {
				$output .= ' ' . get_string('gradingdetailspenalty', 'qbehaviour_adaptiveweighted', array('lastpenalty' => format_float($lastpenalty, $options->markdp), 'max' => $mark->max));
			}
		}
			
			//$output .= ' ' . get_string('gradingdetailspenalty', 'qbehaviour_adaptive', format_float($qa->get_last_behaviour_var('sumpenalty', 0), $options->markdp));
	
		return $output;
	}
}
