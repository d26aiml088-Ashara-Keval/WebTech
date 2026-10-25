-- Optional stored-procedure extension for Practical 8.
-- Run after practical-8.sql has created the StudentHub schema.

USE studenthub;

DROP PROCEDURE IF EXISTS RegisterStudentForEvent;

DELIMITER //
CREATE PROCEDURE RegisterStudentForEvent(
    IN p_student_id INT UNSIGNED,
    IN p_event_id INT UNSIGNED
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM registrations
        WHERE student_id = p_student_id
          AND event_id = p_event_id
    ) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'Student is already registered for this event.';
    END IF;

    INSERT INTO registrations (student_id, event_id)
    VALUES (p_student_id, p_event_id);
END//
DELIMITER ;
