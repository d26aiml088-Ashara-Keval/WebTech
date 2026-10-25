
CREATE DATABASE IF NOT EXISTS studenthub
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub;

CREATE TABLE IF NOT EXISTS students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(254) NOT NULL,
    department VARCHAR(100) NOT NULL,
    study_year TINYINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_email (email),
    CONSTRAINT chk_students_study_year CHECK (study_year BETWEEN 1 AND 8)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS events (
    event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(160) NOT NULL,
    category VARCHAR(60) NOT NULL,
    event_date DATE NOT NULL,
    location VARCHAR(160) NOT NULL,
    description TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_events_event_date (event_date)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled') NOT NULL DEFAULT 'registered',
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_registrations_student_event (student_id, event_id),
    KEY idx_registrations_event_id (event_id),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO students (student_id, full_name, email, department, study_year) VALUES
    (1, 'Aarav Mehta', 'aarav.mehta@studenthub.example', 'Computer Science', 2),
    (2, 'Diya Sharma', 'diya.sharma@studenthub.example', 'Electronics', 3),
    (3, 'Kabir Nair', 'kabir.nair@studenthub.example', 'Mechanical Engineering', 1),
    (4, 'Anaya Iyer', 'anaya.iyer@studenthub.example', 'Computer Science', 4),
    (5, 'Ishaan Patel', 'ishaan.patel@studenthub.example', 'Business', 2),
    (6, 'Meera Das', 'meera.das@studenthub.example', 'Design', 3),
    (7, 'Rohan Kapoor', 'rohan.kapoor@studenthub.example', 'Electronics', 1),
    (8, 'Sara Thomas', 'sara.thomas@studenthub.example', 'Computer Science', 3),
    (9, 'Vivaan Rao', 'vivaan.rao@studenthub.example', 'Mechanical Engineering', 4),
    (10, 'Aditi Sen', 'aditi.sen@studenthub.example', 'Business', 1),
    (11, 'Arjun Kulkarni', 'arjun.kulkarni@studenthub.example', 'Design', 2),
    (12, 'Nisha Reddy', 'nisha.reddy@studenthub.example', 'Computer Science', 1),
    (13, 'Dev Malhotra', 'dev.malhotra@studenthub.example', 'Electronics', 4),
    (14, 'Tara Fernandes', 'tara.fernandes@studenthub.example', 'Mechanical Engineering', 2),
    (15, 'Neel Joshi', 'neel.joshi@studenthub.example', 'Business', 3)
ON DUPLICATE KEY UPDATE
    full_name = VALUES(full_name),
    department = VALUES(department),
    study_year = VALUES(study_year);

INSERT INTO events (event_id, title, category, event_date, location, description) VALUES
    (1, 'Web Development Workshop', 'Workshop', '2027-08-15', 'Innovation Lab', 'Build a responsive website and practice modern HTML, CSS, and JavaScript.'),
    (2, 'Campus Hackathon', 'Technology', '2027-08-25', 'Main Auditorium', 'Team up with other students to turn a creative idea into a working prototype.'),
    (3, 'Photography Walk', 'Community', '2027-09-03', 'North Gate', 'Explore campus through your camera and learn a few composition techniques.'),
    (4, 'Resume Writing Clinic', 'Career', '2027-09-08', 'Career Center', 'Get practical feedback on your resume from the campus careers team.'),
    (5, 'Robotics Open Lab', 'Technology', '2027-09-14', 'Engineering Block, Room 104', 'Meet the robotics club and experiment with beginner-friendly projects.'),
    (6, 'Mindful Study Hour', 'Wellness', '2027-09-19', 'Library Garden', 'Take a calm study break with guided breathing and quiet reflection.'),
    (7, 'Startup Idea Sprint', 'Career', '2027-09-24', 'Business School, Room 12', 'Work in small groups to shape an idea into a simple business concept.'),
    (8, 'Open Mic Evening', 'Community', '2027-10-02', 'Student Commons', 'Share music, poetry, comedy, or simply enjoy performances from campus.'),
    (9, 'Introduction to Data Science', 'Workshop', '2027-10-07', 'Computer Lab 2', 'Discover how data can answer questions with a hands-on beginner session.'),
    (10, 'Green Campus Cleanup', 'Community', '2027-10-12', 'East Courtyard', 'Help care for shared campus spaces and meet fellow volunteers.')
ON DUPLICATE KEY UPDATE
    title = VALUES(title),
    category = VALUES(category),
    event_date = VALUES(event_date),
    location = VALUES(location),
    description = VALUES(description);

INSERT INTO registrations (student_id, event_id, status) VALUES
    (1, 1, 'registered'),
    (1, 2, 'registered'),
    (2, 5, 'registered'),
    (3, 3, 'registered'),
    (4, 9, 'attended'),
    (5, 7, 'registered'),
    (6, 3, 'registered'),
    (7, 5, 'registered'),
    (8, 1, 'attended'),
    (9, 10, 'registered'),
    (10, 7, 'registered'),
    (11, 8, 'registered'),
    (12, 9, 'registered'),
    (13, 2, 'registered'),
    (14, 6, 'cancelled'),
    (15, 4, 'registered')
ON DUPLICATE KEY UPDATE
    status = VALUES(status);
