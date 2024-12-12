# Iptables
Iptables is a user-space utility that allows administrators to configure the IP packet filter rules of the Linux kernel firewall. It controls incoming and outgoing network traffic based on specified rules, providing a robust means of securing network communications. It's commonly used for packet filtering, NAT (Network Address Translation), and logging.

### ❌ Block IP

* Create IP Blacklist
```shell
vi /home/[user]/script/banlist.txt
```

* Show Rule
```shell
iptables -S
```

```
-P INPUT ACCEPT
-P FORWARD ACCEPT
-P OUTPUT ACCEPT
-N f2b-sshd
-A INPUT -p tcp -m multiport --dports 22 -j f2b-sshd
-A INPUT -s 216.244.66.198/32 -j DROP
-A INPUT -s 216.244.66.239/32 -j DROP
-A INPUT -s 216.244.66.205/32 -j DROP
-A INPUT -s 163.172.71.0/24 -j DROP
-A INPUT -s 104.131.147.112/32 -j DROP
-A INPUT -s 54.36.148.0/24 -j DROP
-A INPUT -s 54.36.149.0/24 -j DROP
-A INPUT -s 46.229.168.0/24 -j DROP
-A f2b-sshd -j RETURN
```

* Show Specific Rule
```shell
iptables -S INPUT
```

* Check Iptables Rule Before
```shell
iptables -L -v -n 
```

* Check Configure
```shell
iptables-save
```

* Set Variable from Blacklist
```shell
ban=$(more banlist.txt) 
```

* Check Loop before Execute
```shell
for i in $ban; do echo iptables -A INPUT -s $i -j DROP ; done
```

* Execute Script
```shell
for i in $ban; do iptables -A INPUT -s $i -j DROP ; done
```

* Check Iptables Rule After
```shell
iptables -L -v -n 
```

* Check Website Log
```shell
cd /var/www/[website]/log
iptables -L -v -n
```

* Drop Rules
```shell
iptables -D INPUT -s 1.2.3.4 -j DROP
```

* Source & Destination
```shell
iptables -A INPUT -i eth0 192.168.1.100 -d 222.222.222.222 -p tcp -dport 80 -j ACCEPT
```

### ©️ Credit
https://www.digitalocean.com/community/tutorials/how-to-list-and-delete-iptables-firewall-rules