# Team testing guide

Use this to check that each flow and button works. Go in order. The first part is one patient, from the public website through signup, booking, the clinic visit, payment, and logout. Later numbers cover extra buttons on staff, dentist, and admin that are outside that one visit.

## Before you start

- Open `http://localhost/Dental_Clinic_System/` (it opens the clinic homepage).
- You need four logins: a **new email you can open** (signup and OTP), plus **staff**, **dentist**, and **admin**.
- Use names like `Test Patient` so real records are easy to tell apart.
- For each step, do the action, then check the expected result. Mark **Pass** or **Fail**.
- If it fails, write the step number, the account you used, the button you clicked, what you saw, and a screenshot.
- Do not click **Delete Account** for real, and do not reset 2FA on a real admin.

## A. Patient, start to end

### Public website

1. Open the homepage. **Expected:** Home, About Us, Services, FAQ, Contact Us, Sign Up, and Login are visible.
2. Click Home, About Us, Services, FAQ, and Contact Us. **Expected:** the page moves to that section each time.
3. Narrow the window to phone width and click the menu button, then click it again. **Expected:** the menu opens, then closes.
4. Click **Explore Services**. **Expected:** the services area is shown.
5. Click **Explore All Services**. **Expected:** the full service list is shown.
6. Click **Learn more** on one service. **Expected:** the service detail page opens with that service name.
7. Return to the homepage and click **Inquire now**. **Expected:** the contact form is in view.
8. Fill Contact Us (first name, last name, email, phone starting with `09`, message) and send. **Expected:** a success message. Staff should see this inquiry later.
9. Fill **Book Now** with a brand-new name and email and submit. **Expected:** the registration form opens with that name and email already filled. If the email already has an account, a welcome-back prompt appears instead. Use a new email here.

### Sign up

10. On registration, click **Next** with required fields empty. **Expected:** the form stays and shows what is missing.
11. Fill phone (`09...`), gender, birth date, address, emergency contact, password, and confirm password, then finish with **Register**. **Expected:** an OTP screen appears.
12. Click **Resend**. **Expected:** a new code is sent and the page does not crash.
13. Enter a wrong OTP and confirm. **Expected:** an error, and no account is created.
14. Enter the correct OTP and confirm. **Expected:** the account is created and you reach the patient portal or the login screen.
15. Log out if you are already inside.

### Login and password

16. On the homepage, click **Login**. **Expected:** the login form opens.
17. Click the eye icon on the password. **Expected:** the password shows, then hides.
18. Click the captcha refresh. **Expected:** the captcha image changes.
19. Log in with a wrong password. **Expected:** an error, and you stay on login.
20. Log in with the new patient and the correct captcha. **Expected:** the patient dashboard opens. If a 2FA screen appears, enter the code and confirm you still reach the dashboard.
21. Open **Forgot password**, enter the patient email, and send the code. **Expected:** a code entry screen.
22. Enter a wrong code. **Expected:** an error.
23. Enter the correct code, set a new password, and save. **Expected:** success, and login works with the new password.
24. Log in again with the new password. **Expected:** the dashboard opens.

### Patient dashboard

25. Confirm the sidebar shows Dashboard, Inquiry, Appointments, Records, Billing, and Settings.
26. Click each sidebar item once, then return to Dashboard. **Expected:** each page loads. No blank screen and no error page.
27. Open the profile menu and click **Profile**. **Expected:** the profile page opens.
28. Click the notification bell. **Expected:** the list opens. If an item exists, click it. **Expected:** it opens the related page.
29. On the dashboard calendar, click previous month and next month. **Expected:** the month changes.
30. If a day has an appointment, click it. **Expected:** a detail popup opens and can be closed.
31. Click **Book** and **View All**. **Expected:** Appointments opens.
32. Click the quick actions for appointments, inquiry, and medical records. **Expected:** each opens the matching page.
33. Click the floating **Messages** button, then close it. **Expected:** the chat opens, then closes.

### Inquiry

34. Open **Inquiry**. **Expected:** the conversation screen loads.
35. Type a message and send. **Expected:** the message appears in the thread.
36. Attach a small image and send. **Expected:** the image appears and can be opened.
37. Attach a small file and send. **Expected:** a file link appears and opens.
38. Open conversation information, then go back. **Expected:** the info panel opens, then the chat is still there.
39. On a **staff** login, open **Inquiries** and reply to this patient. **Expected:** the reply shows in the patient chat (reopen the chat if it does not update live).

### Book an appointment

40. Open **Appointments** and click **Book Appointment**. **Expected:** the booking form is shown.
41. Click **Upcoming** and **Past**. **Expected:** each list loads. An empty list is fine.
42. Pick a date. **Expected:** the date stays selected.
43. Choose a service. **Expected:** time slots load. Open slots can be clicked. Booked slots cannot.
44. Click an open slot. **Expected:** it stays selected.
45. Submit the booking and click **Great, Thank You**. **Expected:** a Booking Confirmed message, then the popup closes.
46. Open **Upcoming**. **Expected:** the new appointment is listed with a status.
47. Return to **Dashboard**. **Expected:** the same appointment is in upcoming appointments.

### Staff receives the patient

48. **Staff** logs in. **Expected:** the staff dashboard opens.
49. Staff opens **Appointments**. **Expected:** the patient’s booking is in the list.
50. If the row still needs approval, click **Approve**. **Expected:** the status becomes approved.
51. Open the row menu, click **Mark as Arrived**, and confirm. **Expected:** the patient is marked arrived.
52. Open **Queue Management**. **Expected:** the patient is in the queue.
53. Open the row menu and click **View**. **Expected:** patient details open and can be closed.
54. If **Assign Doctor** is available, assign the dentist who will run this test and click **Assign**. **Expected:** that dentist is saved on the row.

### Dentist visit

55. **Dentist** logs in and opens **Queue Management**. **Expected:** the arrived patient is listed.
56. Open the row menu, click **Start Procedure**, and confirm. **Expected:** the status becomes In Procedure.
57. Open **Consultation** for that patient. **Expected:** the consultation page loads.
58. Click each tab: Informed Consent, Dental Examination, X-Ray, Notes, Dental & Medical History, Diagnosis, Treatment Plan, Prescription, Medical Certificate. **Expected:** each tab shows its form.
59. On Dental Examination, mark at least one tooth and save if that tab has a save button. **Expected:** the mark is still there after you leave the tab and come back.
60. On Treatment Plan, enter a short plan and click **Save Treatment Plan**. **Expected:** success, and the plan is still there after refresh.
61. On Prescription, add one medicine and click **Save Prescription**. **Expected:** the prescription is saved.
62. On Informed Consent, draw a signature, click **Save Signature**, then **Save Consent**. **Expected:** both save.
63. Complete the consultation. **Expected:** the visit is marked complete.
64. If the queue row is still In Procedure, open it and click **Complete**. **Expected:** the row moves to Completed.
65. Open **Prescriptions**. **Expected:** the prescription from this visit is listed and can be opened.

### Payment and what the patient sees after

66. **Staff** opens **Payment**. **Expected:** this visit has a bill or a pending payment.
67. Open the row, open **Record Payment**, enter the full amount and a payment method, and save. **Expected:** the status becomes Paid.
68. Staff opens **Patient Records**, searches the test patient, and opens the record. **Expected:** the visit, treatment plan, and prescription are visible.
69. **Patient** returns to the portal and opens **Appointments**, then **Past**. **Expected:** the visit is listed as completed or past.
70. Patient opens **Records** and clicks **Visits**, **Prescriptions**, and **Treatment Plans**. **Expected:** each tab shows the items from this visit.
71. On Visits, click **View full record**, then close it. **Expected:** the full record opens and closes.
72. If an X-ray was uploaded, open it and close the viewer. **Expected:** the image opens and closes.
73. Patient opens **Billing**. **Expected:** the payment shows as Paid, with the amount staff recorded.
74. Patient opens the notification bell. **Expected:** appointment, reply, or payment notices can be opened.

### Profile, settings, logout

75. Patient opens **Profile**, clicks **Edit Personal Information**, changes one field, and clicks **Save Changes**. **Expected:** the new value stays after refresh. Change it back afterward.
76. Click the pencil on the phone, then **Cancel**. **Expected:** the phone number is unchanged.
77. Change the profile photo and click **Save Photo**. **Expected:** the new photo shows in the header.
78. Open **Settings**, change the password, log out, and log in with the new password. **Expected:** login works.
79. Toggle Email Notifications and SMS Notifications and click **Save Preferences**. **Expected:** success, and the same choices are still checked after refresh.
80. Change the session timeout and click **Save Session Preference**. **Expected:** success, and the value stays after refresh.
81. Click **Delete Account**, then **Cancel**. **Expected:** the account still works.
82. Open the profile menu and click **Logout**. **Expected:** you return to the public site. Refreshing the dashboard asks you to log in again.

### Easy misses on the public site

83. From Login, start **Forgot password**, then click **Back to login**. **Expected:** the login form is shown.
84. From Sign Up, click **Login**. **Expected:** the login form is shown.
85. Try Sign Up again with the same email. **Expected:** the system says the account already exists.
86. On the homepage, use **Book Now** with that same email. **Expected:** a welcome-back prompt, and a second account is not created.

## B. Staff — remaining buttons

87. On the staff dashboard, click every card or shortcut that is a link. **Expected:** each opens the matching page.
88. Open the notification bell and click one item. **Expected:** it opens the related page.
89. Open **Profile** from the header menu, then return. Leave **Logout** until the end of staff testing.
90. Open **New Admission**, fill a walk-in named `Test Walkin`, and click **Submit**. **Expected:** the patient is created and can be found in Patient Records or the queue.
91. Open New Admission again and move through the steps with Next and Back, then leave without a second submit. **Expected:** you are not stuck on a loading popup.
92. In **Inquiries**, open the website message from step 8 and the patient chat from step 35, and reply to each. **Expected:** send succeeds, and the patient chat shows the reply.
93. If a Facebook inquiry is in the list, open it and send a short reply. **Expected:** the reply sends, or a clear error if Messenger is not connected. Write down which one happened.
94. In **Queue**, use search and the status filter (waiting, in procedure, on hold, completed). **Expected:** the list matches the filter.
95. If there is more than one page, click **Previous** and **Next**. **Expected:** the page changes and the “Showing …” text updates.
96. On an In Procedure row, use the menu to put the patient **On Hold**, then bring them back. **Expected:** the row moves between On Hold and In Procedure.
97. Open **Dentist Schedule** and change the day or dentist if those controls are there. **Expected:** the schedule redraws.
98. In **Appointments**, click **+ New Appointment**, book the test patient, and save. **Expected:** it appears on the list.
99. On that extra appointment, open the menu, click **Cancel**, enter a reason, and confirm. **Expected:** the status becomes Cancelled.
100. Use the appointments calendar previous/next and any status tabs. **Expected:** the view changes.
101. In **Patient Records**, search, open a patient, and use view or edit. **Expected:** details open, and save shows success.
102. Open **Prescriptions**, search, and open one. **Expected:** it matches what the dentist saved.
103. In **Payment**, open **History** on the paid bill. **Expected:** the payment from step 67 is listed. Open a pending bill and close it without paying. **Expected:** it stays unpaid.
104. On **Profile**, save one harmless change, then change it back.
105. Narrow the window and click the menu button. **Expected:** the sidebar opens and closes.
106. Click **Logout**. **Expected:** staff pages cannot be used until login again.

## C. Dentist — remaining buttons

107. On the dentist dashboard, click every card or shortcut that is a link. **Expected:** each opens the matching page.
108. Open the notification bell and click one item. **Expected:** it opens the related page.
109. Open **Patient Records**, search the test patient, and open the visit. **Expected:** notes, plan, and prescription from the visit are there.
110. Open **Treatment Plan**. **Expected:** the plan saved in consultation is listed and can be opened.
111. Open **Appointments**. **Expected:** the completed visit and the cancelled test appointment show the right status. Use any filter. **Expected:** the list updates.
112. In **Queue**, use search, status filters, and pagination. **Expected:** the list updates the same way it did for staff.
113. On a waiting patient assigned to someone else, try **Start Procedure**. **Expected:** it either starts, or the menu shows **Start Procedure (Locked)** and does not start. Write down which one you saw.
114. Open **Profile**, save a harmless change, and change it back.
115. Narrow the window and click the menu button. **Expected:** the sidebar opens and closes.
116. Click **Logout**. **Expected:** dentist pages cannot be used until login again.

## D. Admin

117. Log in as admin. **Expected:** the dashboard loads, and the sidebar storage line shows a percent or a dash.
118. Open **Dashboard** and click any card that is a link. **Expected:** it opens the matching page.
119. Open **Patient Records**, search the test patient, and open the record. **Expected:** the visit is visible.
120. Open **Patient Logs**, use search or filter if they are there, and open one row. **Expected:** the log details show.
121. Open **Queue Management**. **Expected:** the test patient is under the status staff and dentist left them in. Filters change the list.
122. Open **Appointments**. **Expected:** the booked, completed, and cancelled test rows are listed. Open one. Use **Approve** only on a row that is still pending. Use **Cancel** only on a test row, with a reason.
123. Open **Payment**. **Expected:** the paid test bill is visible and can be opened. Do not record a second payment on it.
124. Open **Payment Logs** and search or filter by today’s date. **Expected:** the payment from step 67 is found.
125. Open **Services List**, click **Add Service**, and save one named `ZZ Test Service`. Edit its price and save. **Expected:** the new price shows. Deactivate or remove it afterward so patients do not see it. Check the public services list and confirm it is gone.
126. Open **Analytics** and change the date range or chart filter. **Expected:** the charts reload and do not stay on a spinner.
127. Open **Reports** and use export or print if that button is there. **Expected:** a file downloads or a print view opens.
128. Open **User Management**, click **+ Add New User**, create a test staff user, and **Save**. **Expected:** the user is in the list. Open **View**, then **Close**. Edit the user and save. On this test user only, open **Reset 2FA** and click **Cancel** first. **Expected:** 2FA is unchanged. Do not reset 2FA on a real admin.
129. Open **Content**, change one line of welcome text, save, and check the public homepage. **Expected:** the new text is there. Change it back and save again.
130. Open **Audit Trail** and search. **Expected:** recent test actions (login, payment, or the new user) appear.
131. Open **Archive** and click each tab. Search. Do not permanently delete real records. If you archive the `ZZ Test Service` or the test user, use **Restore** on that test item only. **Expected:** restore puts it back and the list updates.
132. Open **Settings**, change one safe option, save, refresh, confirm it stayed, then set it back.
133. Open the notification bell, open **Profile**, save, then click **Logout**. **Expected:** admin pages cannot be used until login again.
134. Click the sidebar group headers (Main, Clinic Operations, Billing & Services, Insights & Data, System Administration). **Expected:** each group collapses and expands.
135. Narrow the window, open the menu, then click the dark overlay. **Expected:** the sidebar opens, then closes.

## What to send back

Send only the failed step numbers. For each one, include the account, the button name, what you expected, what happened, and a screenshot.
