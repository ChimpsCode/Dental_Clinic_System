<?php
$rxLogo = $rxLogo ?? '../../assets/images/Logo.png';
?>
<div class="rx-paper" id="rxPaper">
    <div class="rx-head">
        <img class="rx-logo" src="<?php echo htmlspecialchars($rxLogo); ?>" alt="RF Dental">
        <div class="rx-head-text">
            <h1 class="rx-clinic-name">RF DENTAL CLINIC</h1>
            <p class="rx-doctors">DR. REX R. GAABUCAYAN &amp; DR. FLORALYN T. NATU-EL</p>
            <div class="rx-meta-grid">
                <div class="rx-meta-item"><i class="fas fa-map-marker-alt"></i><span>Celeste Bldg., Max Suniel St., Brgy. Carmen, CDO</span></div>
                <div class="rx-meta-item"><i class="fas fa-phone"></i><span>0967 187 8603</span></div>
                <div class="rx-meta-item"><i class="fas fa-clock"></i><span>Mon-Sat: 9am - 5pm</span></div>
                <div class="rx-meta-item"><i class="fas fa-envelope"></i><span>RFDC2023@gmail.com</span></div>
                <div class="rx-meta-item"></div>
                <div class="rx-meta-item"><i class="fab fa-facebook"></i><span>RF Dental Clinic CDO</span></div>
            </div>
        </div>
    </div>
    <div class="rx-double-rule"></div>

    <div class="rx-field-row">
        <div class="rx-field grow"><span>Name:</span><span class="rx-underline" id="rxName"></span></div>
        <div class="rx-field"><span>Age:</span><span class="rx-underline rx-age" id="rxAge"></span></div>
        <div class="rx-field"><span>Sex:</span><span class="rx-underline rx-sex" id="rxSex"></span></div>
    </div>
    <div class="rx-field-row">
        <div class="rx-field grow"><span>Address:</span><span class="rx-underline" id="rxAddress"></span></div>
        <div class="rx-field"><span>Date:</span><span class="rx-underline rx-date" id="rxDate"></span></div>
    </div>

    <div class="rx-body">
        <div class="rx-symbol">Rx</div>
        <div class="rx-meds">
            <div id="rxMedicationsContainer"></div>
            <div class="rx-notes" id="rxInstructionsBox"><span id="rxInstructions"></span></div>
        </div>
    </div>

    <div class="rx-foot">
        <div class="rx-follow">
            Follow up Date:
            <span class="rx-underline" id="rxFollowup"></span>
        </div>
        <div class="rx-sign">
            <div class="rx-sign-line" id="rxDoctor"></div>
            <div class="rx-sign-caption">Dentist's Name</div>
            <div class="rx-lic"><span>Lic. No.:</span><span class="rx-underline"></span></div>
            <div class="rx-lic"><span>PTR No.:</span><span class="rx-underline"></span></div>
        </div>
    </div>
</div>
