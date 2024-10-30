<?php
/**
 * CellarWeb ChatBot Blocker add-in for CellarWeb Privacy and Security plugin
 *		- only core functions needed; from the CellarWeb Chatbot Blocker plugin (pending release)
 *
 * Copyright 2023 by Rick Hellewell / CellarWeb  https://www.CellarWeb.com
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License, version 2, as
 * published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 *
 * "CellarWeb ChatBot Blocker" is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * any later version.
 *
 * "CellarWeb ChatBot Blocker" is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * "along with CellarWeb ChatBot Blocker". If not, see http://www.gnu.org/licenses/gpl-2.0.txt.
 *

/**
 * Dynamically create the robots.txt file with our saved content.
 *
 * @since   1.00
 * @uses    get_option
 * @uses    esc_attr
 * @param string $output The contents of robots.txt filtered.
 * @param string $public The visibility option.
 * @return  string
 */
function CWPS_robots_option_content($output, $public) {
	$content = get_option('CWPS_chatbot_content');
	if ($content) {
		$output = esc_attr(wp_strip_all_tags($content));
	}
	return $output;
}

/**
 * Deactivation hook. Deletes our option containing the robots.txt content.
 *
 * @since   1.00
 * @uses    delete_option
 * @return  void
 */
function CWPS_chatbot_deactivation() {
	delete_option('CWPS_chatbot_content');
}

/**
 * Activation hook.  Adds the option we'll be using.
 *
 * @since   1.00
 * @uses    add_option
 * @return  void
 */
function CWPS_chatbot_activation() {
	add_option('CWPS_chatbot_content', false);

	// Backwards compatibility.
	$old = get_option('cw_chatbot_block_content');
	if (false !== $old) {
		update_option('CWPS_chatbot_content', $old);
		delete_option('cw_chatbot_block_content');
	}
}
