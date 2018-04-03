#include <stdio.h>
#include <signal.h>
#include <unistd.h>
#include <stdlib.h>
#include <fcntl.h>
#include <string.h>
#include <time.h>


void signal_handler(int sig);
void AddMessage2Log(char *strText);
void setInterval(int num, int report);

int pid_handle;

//    struct of report intervals
typedef struct intervalList
{
    int report1,
        report2,
        report3,
        report4;
} intervals;

typedef int bool;
#define true 1
#define false 0

int main(int argc, char *argv[])
{
	int second=0,i=0;
	int pid,sid;
	intervals list;
	struct sigaction sig_act;
	sigset_t sig_set;
	char str[255];

	AddMessage2Log("Daemon starting up");

	//    Check if is childrenprocess id is set
	if (getppid()!=1)
	{
		//    set the signals that we want to block
		sigemptyset(&sig_set);
		//    ignore child - i.e. we don't neet to wait for it
		sigaddset(&sig_set, SIGCHLD);
		//    ignore tty stop signals
		sigaddset(&sig_set, SIGTSTP);
		//    ignore tty background writes
		sigaddset(&sig_set, SIGTTOU);
		//    ignore tty background reads
		sigaddset(&sig_set, SIGTTIN);
		//    block the above specified signals
		sigprocmask(SIG_BLOCK, &sig_set, NULL);

		sig_act.sa_handler = signal_handler;
		sigemptyset(&sig_act.sa_mask);
		sig_act.sa_flags = 0;

		sigaction(SIGHUP, &sig_act, NULL);
		sigaction(SIGTERM,&sig_act, NULL);
		sigaction(SIGINT, &sig_act, NULL);

		/* fork */
		pid = fork();		
		if (pid < 0)
		{
			//    could not work
			exit(EXIT_FAILURE);
		}
		if (pid > 0)
		{
			sprintf(str,"Child process created: %d",pid);
			AddMessage2Log(str);
			//    child process created, so exit parent process
			exit(EXIT_SUCCESS);
		}
		umask(0);
		//    create new process group
		sid = setsid();
		if (sid < 0)
		{
			exit(EXIT_FAILURE);
		}
		//     close all descriptors
		for (i=getdtablesize(); i>=0; --i)
		{
			close(i);
		}
		//    STDIN
		i = open("/dev/null", O_RDWR);
		//    STDOUT
		dup(i);
		//    STDERR
		dup(i);

		
		chdir("/");

		/* Daemon-specific initialization goes here */
		list.report1 = atoi(argv[1]);
		list.report2 = atoi(argv[2]);
		list.report3 = atoi(argv[3]);
		list.report4 = atoi(argv[4]);

		AddMessage2Log("Daemon running");

		while (++second)
		{
	        if (second % list.report1 == 0)
	        {
	            setInterval(1, list.report1);
	        }
	        if (second % list.report2 == 0)
	        {
	            setInterval(2, list.report2);
	        }
	        if (second % list.report3 == 0)
	        {
	            setInterval(3, list.report3);
	        }
	        if (second % list.report4 == 0)
	        {
	            setInterval(4, list.report4);
	        }
			if (second % 10 == 0)
			{
			    second=0;
			}
			sleep(1);
		}
	}
	return 0;
}
void signal_handler(int sig)
{
	char buffer[255];
	switch (sig)
	{
		case SIGHUP:
			sprintf(buffer,"Received SIGHUP signal. %s",strsignal(sig));
			AddMessage2Log(buffer);
			break;
		case SIGINT:
		case SIGTERM:
			sprintf(buffer,"Daemon exiting %s",strsignal(sig));
			AddMessage2Log(buffer);
			/*
			close(pid_handle);
			*/
			exit(EXIT_SUCCESS);
			break;
		default:
			sprintf(buffer,"Unhandled signal %s",strsignal(sig));
			AddMessage2Log(buffer);
			break;
	}
}
void AddMessage2Log(char *strText)
{
	FILE *fd = fopen("/srv/bitrix/www/bitrix/components/crediteurope/terminal/logs/cdaemon.log","a");
	fprintf(fd,"%s\n",strText);
	fclose(fd);
}
void setInterval(int num, int report)
{
    time_t rawtime;
    struct tm *timeinfo;
    char strTime[80];
    FILE *fd,
         *input;
    extern FILE *popen();
    char buffer[1024],
         command[100],
         string[255];
    size_t ln = 0;

    time(&rawtime);
    timeinfo = localtime(&rawtime);
    strftime(strTime,80,"%d.%m.%Y %H:%M:%S",timeinfo);

    fd = fopen("/srv/bitrix/www/bitrix/components/crediteurope/terminal/logs/cdaemon.log","a");

    sprintf(command,"%s %s REPORT%d","php -f","/srv/bitrix/www/applications/terminal/index.php",num);
    if ((input = popen(command,"r")))
    {
        while (fgets(buffer, sizeof(buffer)-1,input)!=NULL);
        pclose(input);
        if (strlen(buffer) > 0)
        {
            fwrite("ERROR: ",7,1,fd);
            fwrite(buffer,strlen(buffer),1,fd);
        }
    }
    sprintf(string,"\n[%s] DAEMON: REPORT%d is launched\n",strTime,num);
    fwrite(string,strlen(string),1,fd);

    fclose(fd);
}