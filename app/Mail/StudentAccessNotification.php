<?php
 
 namespace App\Mail;
 
 use Illuminate\Bus\Queueable;
 use Illuminate\Mail\Mailable;
 use Illuminate\Queue\SerializesModels;
 
 class StudentAccessNotification extends Mailable
 {
     use Queueable, SerializesModels;
 
     public $user;
     public $cohortName;
     public $status;
     public $reason;
 
     /**
      * Create a new message instance.
      */
     public function __construct($user, $cohortName, $status, $reason = null)
     {
         $this->user = $user;
         $this->cohortName = $cohortName;
         $this->status = $status;
         $this->reason = $reason;
     }
 
     /**
      * Build the message.
      */
     public function build()
     {
         $subject = $this->status === 'active' 
             ? "Access Restored: {$this->cohortName}" 
             : "Access Update: {$this->cohortName}";
 
         return $this->subject($subject)
                     ->markdown('emails.student_access');
     }
 }
