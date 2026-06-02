pipeline {
    agent any
    
    // triggers {
    //     pollSCM('H/5 * * * *')
    // }
    
    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }
        
        stage('Deploy via WSL') {
            steps {
                withCredentials([string(credentialsId: 'server-password', variable: 'ANSIBLE_PASS')]) {
                    bat """
                        wsl -d kali-linux bash -c "cd /mnt/c/xampp/htdocs/terrachain-ansible && sshpass -p '${ANSIBLE_PASS}' ansible-playbook -i inventory/hosts.ini site.yml --user=webadmin --ask-become-pass"
                    """
                }
            }
        }
    }
}