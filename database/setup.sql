-- =====================================================================
-- Company Staff Portal - database setup
-- Based on the COMPANY schema from Elmasri & Navathe,
-- "Fundamentals of Database Systems" (Chapter 5, Figures 5.5-5.7)
-- and the PHP database examples in Chapter 11.
--
-- Run once as an admin user (XAMPP default: root with no password):
--   mysql -u root -p < database/setup.sql
--
-- What it does:
--   1. Creates a dedicated database: company_portal
--   2. Creates a dedicated, least-privilege account for the PHP app
--   3. Creates the COMPANY tables + an audit table
--   4. Loads the textbook's sample data
-- =====================================================================

DROP DATABASE IF EXISTS company_portal;
CREATE DATABASE company_portal
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

-- The PHP app connects with this account, never with root.
-- It can only read and write rows in company_portal: it cannot
-- create or drop tables, or touch any other database.
-- CHANGE THE PASSWORD here and in config/config.php.
CREATE USER IF NOT EXISTS 'portal_app'@'localhost' IDENTIFIED BY 'ChangeMe_Portal2026!';
GRANT SELECT, INSERT, UPDATE, DELETE ON company_portal.* TO 'portal_app'@'localhost';
FLUSH PRIVILEGES;

USE company_portal;

-- ---------------------------------------------------------------------
-- COMPANY schema (textbook Figure 5.7, adapted for MariaDB/InnoDB)
-- ---------------------------------------------------------------------
CREATE TABLE DEPARTMENT (
  Dname          VARCHAR(15)  NOT NULL,
  Dnumber        INT          NOT NULL,
  Mgr_ssn        CHAR(9)      NOT NULL,
  Mgr_start_date DATE,
  PRIMARY KEY (Dnumber),
  UNIQUE (Dname)
) ENGINE=InnoDB;

CREATE TABLE EMPLOYEE (
  Fname     VARCHAR(15)   NOT NULL,
  Minit     CHAR(1),
  Lname     VARCHAR(15)   NOT NULL,
  Ssn       CHAR(9)       NOT NULL,
  Bdate     DATE,
  Address   VARCHAR(30),
  Sex       CHAR(1),
  Salary    DECIMAL(10,2),
  Super_ssn CHAR(9),
  Dno       INT           NOT NULL DEFAULT 1,
  PRIMARY KEY (Ssn),
  CONSTRAINT chk_sex    CHECK (Sex IN ('M','F')),
  CONSTRAINT chk_salary CHECK (Salary >= 0),
  FOREIGN KEY (Super_ssn) REFERENCES EMPLOYEE(Ssn),
  FOREIGN KEY (Dno)       REFERENCES DEPARTMENT(Dnumber)
) ENGINE=InnoDB;

CREATE TABLE DEPT_LOCATIONS (
  Dnumber   INT          NOT NULL,
  Dlocation VARCHAR(15)  NOT NULL,
  PRIMARY KEY (Dnumber, Dlocation),
  FOREIGN KEY (Dnumber) REFERENCES DEPARTMENT(Dnumber)
) ENGINE=InnoDB;

CREATE TABLE PROJECT (
  Pname     VARCHAR(15)  NOT NULL,
  Pnumber   INT          NOT NULL,
  Plocation VARCHAR(15),
  Dnum      INT          NOT NULL,
  PRIMARY KEY (Pnumber),
  UNIQUE (Pname),
  FOREIGN KEY (Dnum) REFERENCES DEPARTMENT(Dnumber)
) ENGINE=InnoDB;

CREATE TABLE WORKS_ON (
  Essn  CHAR(9)       NOT NULL,
  Pno   INT           NOT NULL,
  Hours DECIMAL(3,1),
  PRIMARY KEY (Essn, Pno),
  CONSTRAINT chk_hours CHECK (Hours IS NULL OR (Hours > 0 AND Hours <= 60)),
  FOREIGN KEY (Essn) REFERENCES EMPLOYEE(Ssn),
  FOREIGN KEY (Pno)  REFERENCES PROJECT(Pnumber)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Portal addition: audit trail of every form submission
-- (successful or rejected), shown on the dashboard.
-- ---------------------------------------------------------------------
CREATE TABLE SUBMISSION_LOG (
  Log_id      INT          NOT NULL AUTO_INCREMENT,
  Form_name   VARCHAR(30)  NOT NULL,
  Outcome     ENUM('saved','updated','rejected') NOT NULL,
  Summary     VARCHAR(255) NOT NULL,
  Client_ip   VARCHAR(45),
  Created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (Log_id)
) ENGINE=InnoDB;

-- ---------------------------------------------------------------------
-- Sample data (textbook Figure 5.6)
-- DEPARTMENT and EMPLOYEE reference each other, so foreign key
-- checks are paused while the starting rows are loaded.
-- ---------------------------------------------------------------------
SET FOREIGN_KEY_CHECKS = 0;

INSERT INTO DEPARTMENT VALUES
  ('Research',       5, '333445555', '1988-05-22'),
  ('Administration', 4, '987654321', '1995-01-01'),
  ('Headquarters',   1, '888665555', '1981-06-19');

INSERT INTO EMPLOYEE VALUES
  ('John',     'B', 'Smith',   '123456789', '1965-01-09', '731 Fondren, Houston, TX',  'M', 30000, '333445555', 5),
  ('Franklin', 'T', 'Wong',    '333445555', '1955-12-08', '638 Voss, Houston, TX',     'M', 40000, '888665555', 5),
  ('Alicia',   'J', 'Zelaya',  '999887777', '1968-01-19', '3321 Castle, Spring, TX',   'F', 25000, '987654321', 4),
  ('Jennifer', 'S', 'Wallace', '987654321', '1941-06-20', '291 Berry, Bellaire, TX',   'F', 43000, '888665555', 4),
  ('Ramesh',   'K', 'Narayan', '666884444', '1962-09-15', '975 Fire Oak, Humble, TX',  'M', 38000, '333445555', 5),
  ('Joyce',    'A', 'English', '453453453', '1972-07-31', '5631 Rice, Houston, TX',    'F', 25000, '333445555', 5),
  ('Ahmad',    'V', 'Jabbar',  '987987987', '1969-03-29', '980 Dallas, Houston, TX',   'M', 25000, '987654321', 4),
  ('James',    'E', 'Borg',    '888665555', '1937-11-10', '450 Stone, Houston, TX',    'M', 55000, NULL,        1);

SET FOREIGN_KEY_CHECKS = 1;

INSERT INTO DEPT_LOCATIONS VALUES
  (1, 'Houston'), (4, 'Stafford'), (5, 'Bellaire'), (5, 'Sugarland'), (5, 'Houston');

INSERT INTO PROJECT VALUES
  ('ProductX',        1,  'Bellaire',  5),
  ('ProductY',        2,  'Sugarland', 5),
  ('ProductZ',        3,  'Houston',   5),
  ('Computerization', 10, 'Stafford',  4),
  ('Reorganization',  20, 'Houston',   1),
  ('Newbenefits',     30, 'Stafford',  4);

INSERT INTO WORKS_ON VALUES
  ('123456789', 1, 32.5), ('123456789', 2, 7.5),
  ('666884444', 3, 40.0),
  ('453453453', 1, 20.0), ('453453453', 2, 20.0),
  ('333445555', 2, 10.0), ('333445555', 3, 10.0),
  ('333445555', 10, 10.0), ('333445555', 20, 10.0),
  ('999887777', 30, 30.0), ('999887777', 10, 10.0),
  ('987987987', 10, 35.0), ('987987987', 30, 5.0),
  ('987654321', 30, 20.0), ('987654321', 20, 15.0),
  ('888665555', 20, NULL);
