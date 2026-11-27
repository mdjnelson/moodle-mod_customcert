@mod @mod_customcert
Feature: Downloading all the issued certificates of an activity in the background
  In order to avoid long page loads when there are many issued certificates
  As a teacher
  I need the zip file of all issued certificates to be generated in the background

  Background:
    Given the following "courses" exist:
      | fullname | shortname | category |
      | Course 1 | C1        | 0        |
    And the following "users" exist:
      | username | firstname | lastname | email                |
      | teacher1 | Teacher   | 1        | teacher1@example.com |
      | student1 | Student   | 1        | student1@example.com |
    And the following "course enrolments" exist:
      | user     | course | role           |
      | teacher1 | C1     | editingteacher |
      | student1 | C1     | student        |
    And the following "activities" exist:
      | activity   | name                 | intro                      | course | idnumber    |
      | customcert | Custom certificate 1 | Custom certificate 1 intro | C1     | customcert1 |
    And I log in as "student1"
    And I am on "Course 1" course homepage
    And I follow "Custom certificate 1"
    And I press "View certificate"
    And I log out

  Scenario: A teacher requests a download of all issued certificates
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Custom certificate 1"
    When I press "Download all issued certificates"
    Then I should see "Your request to download all issued certificates has been queued"
    And I should see "normally removed after 24 hours"
    And I should see "Student 1"
    And I run all adhoc tasks
    And "teacher1" should have received the issued certificates download notification
    And I follow the issued certificates download link sent to "teacher1"
    And the response should be a zip file

  Scenario: A student cannot request or retrieve the archive
    Given I log in as "teacher1"
    And I am on "Course 1" course homepage
    And I follow "Custom certificate 1"
    And I press "Download all issued certificates"
    And I run all adhoc tasks
    And I log out
    And I log in as "student1"
    Then the issued certificates download link sent to "teacher1" should be refused
