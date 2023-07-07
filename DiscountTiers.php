<?php
class DiscountTiers {
	public $settings = array(
		'name' => 'Discount Tiers',
		'admin_menu_category' => 'Ordering',
		'admin_menu_name' => 'Discount Tiers',
		'admin_menu_icon' => '<i class="icon-tag"></i>',
		'description' => 'Configure how much discount to give a user depending on how many active services they have in their account.',
	);
	function admin_area() {
		global $billic, $db;
		if (isset($_GET['Name'])) {
			$discounttier = $db->q('SELECT * FROM `discounttiers` WHERE `name` = ?', urldecode($_GET['Name']));
			$discounttier = $discounttier[0];
			if (empty($discounttier)) {
				err('Discount Tier does not exist');
			}
			$billic->set_title('Admin/Discount Tier ' . safe($discounttier['name']));
			echo '<h1>Discount Tier ' . safe($discounttier['name']) . '</h1>';
			if (isset($_POST['update'])) {
				if (empty($_POST['name'])) {
					$billic->error('Name can not be empty', 'name');
				} else {
					$name_check = $db->q('SELECT COUNT(*) FROM `discounttiers` WHERE `name` = ?', $_POST['name']);
					if ($name_check[0]['COUNT(*)'] > 1) {
						$billic->error('Name is already in use by a different Discount Tier', 'name');
					}
				}
				if (empty($billic->errors)) {
					$db->q('UPDATE `discounttiers` SET `name` = ?, `numservices` = ?, `discount` = ? WHERE `name` = ?', $_POST['name'], $_POST['numservices'], $_POST['discount'], urldecode($_GET['Name']));
					$billic->redirect('/Admin/DiscountTiers/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			echo '<form method="POST"><table class="table table-striped"><tr><th colspan="2">Discount Tier Settings</th></td></tr>';
			echo '<tr><td width="125">Name</td><td><input type="text" class="form-control" name="name" value="' . (isset($_POST['name']) ? safe($_POST['name']) : safe($discounttier['name'])) . '"></td></tr>';
			echo '<tr><td width="125">Number of Services</td><td><input type="text" class="form-control" name="numservices" value="' . (isset($_POST['numservices']) ? safe($_POST['numservices']) : safe($discounttier['numservices'])) . '"></td></tr>';
			echo '<tr><td width="125">Discount</td><td><div class="input-group" style="width: 100px"><input type="text" class="form-control" name="discount" value="' . (isset($_POST['discount']) ? safe($_POST['discount']) : safe($discounttier['discount'])) . '"><span class="input-group-addon" id="basic-addon2">%</div></div></td></tr>';
			echo '</td></tr><tr><td colspan="4" align="center"><input type="submit" class="btn btn-success" name="update" value="Update &raquo;"></td></tr></table></form>';
			return;
		}
		if (isset($_GET['New'])) {
			$title = 'New Discount Tier';
			$billic->set_title($title);
			echo '<h1>' . $title . '</h1>';
			$billic->module('FormBuilder');
			$form = array(
				'name' => array(
					'label' => 'Name',
					'type' => 'text',
					'required' => true,
					'default' => '',
				) ,
			);
			if (isset($_POST['Continue'])) {
				$billic->modules['FormBuilder']->check_everything(array(
					'form' => $form,
				));
				if (empty($billic->errors)) {
					$db->insert('discounttiers', array(
						'name' => $_POST['name'],
					));
					$billic->redirect('/Admin/DiscountTiers/Name/' . urlencode($_POST['name']) . '/');
				}
			}
			$billic->show_errors();
			$billic->modules['FormBuilder']->output(array(
				'form' => $form,
				'button' => 'Continue',
			));
			return;
		}
		if (isset($_GET['Delete'])) {
			$db->q('DELETE FROM `discounttiers` WHERE `name` = ?', urldecode($_GET['Delete']));
			$billic->status = 'deleted';
		}
		$total = $db->q('SELECT COUNT(*) FROM `discounttiers`');
		$total = $total[0]['COUNT(*)'];
		$pagination = $billic->pagination(array(
			'total' => $total,
		));
		echo $pagination['menu'];
		$discounttiers = $db->q('SELECT * FROM `discounttiers` ORDER BY `discount` ASC LIMIT ' . $pagination['start'] . ',' . $pagination['limit']);
		$billic->set_title('Admin/Discount Tier');
		echo '<h1><i class="icon-tag"></i> Discount Tiers</h1>';
		echo '<a href="New/" class="btn btn-success"><i class="icon-plus"></i> New Discount Tier</a>';
		$billic->show_errors();
		echo '<div style="float: right;padding-right: 40px;">Showing ' . $pagination['start_text'] . ' to ' . $pagination['end_text'] . ' of ' . $total . ' Discount Tiers</div>';
		echo '<table class="table table-striped"><tr><th>Name</th><th>Number of Services</th><th>Discount</th><th>Actions</th></tr>';
		if (empty($discounttiers)) {
			echo '<tr><td colspan="20">No Discount Tiers matching filter.</td></tr>';
		}
		foreach ($discounttiers as $discounttier) {
			echo '<tr><td><a href="/Admin/DiscountTiers/Name/' . urlencode($discounttier['name']) . '/">' . safe($discounttier['name']) . '</a></td><td>' . $discounttier['numservices'] . '</td><td>' . $discounttier['discount'] . '%</td><td>';
			echo '<a href="/Admin/DiscountTiers/Name/' . urlencode($discounttier['name']) . '/" class="btn btn-primary btn-xs"><i class="icon-edit-write"></i> Edit</a>';
			echo '&nbsp;<a href="/Admin/DiscountTiers/Delete/' . urlencode($discounttier['name']) . '/" class="btn btn-danger btn-xs" title="Delete" onClick="return confirm(\'Are you sure you want to delete?\');"><i class="icon-remove"></i> Delete</a>';
			echo '</td></tr>';
		}
		echo '</table>';
	}
	function calc_discount_tier($user) {
		global $billic, $db;
		if ($user['discount'] != 0) {
			return $user['discount'];
		}
		$numservices = $db->q('SELECT COUNT(*) FROM `services` WHERE `userid` = ? AND `domainstatus` = \'Active\' AND `amount` > 0 AND `module` != `domain`', $user['id']);
		$numservices = $numservices[0]['COUNT(*)'];
		$discounttier = $db->q('SELECT `discount` FROM `discounttiers` WHERE `numservices` < ? ORDER BY `numservices` DESC LIMIT 1', $numservices);
		if (empty($discounttier)) return 0;
		$discounttier = $discounttier[0];
		return $discounttier['discount'];
	}
}
