ALTER TABLE %PREFIX%users MODIFY `password` varchar(255) NOT NULL DEFAULT '';
ALTER TABLE %PREFIX%users_valid MODIFY `password` varchar(255) NOT NULL;