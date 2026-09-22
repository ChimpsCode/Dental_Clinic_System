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
        <div class="rx-field grow"><label for="rxName">Name:</label><input type="text" class="rx-input" id="rxName" autocomplete="off"></div>
        <div class="rx-field"><label for="rxAge">Age:</label><input type="text" class="rx-input rx-age" id="rxAge" autocomplete="off"></div>
        <div class="rx-field"><label for="rxSex">Sex:</label><input type="text" class="rx-input rx-sex" id="rxSex" autocomplete="off"></div>
    </div>
    <div class="rx-field-row">
        <div class="rx-field grow"><label for="rxAddress">Address:</label><input type="text" class="rx-input" id="rxAddress" autocomplete="off"></div>
        <div class="rx-field"><label for="rxDate">Date:</label><input type="text" class="rx-input rx-date" id="rxDate" autocomplete="off"></div>
    </div>

    <div class="rx-body">
        <div class="rx-symbol">Rx</div>
        <div class="rx-meds">
            <textarea class="rx-textarea" id="rxMedicationsContainer" rows="2" placeholder="Write the prescription here..."></textarea>
            <textarea class="rx-textarea rx-notes-input" id="rxInstructions" rows="2" placeholder="Additional notes / instructions..."></textarea>
        </div>
    </div>

    <div class="rx-foot">
        <div class="rx-follow">
            <label for="rxFollowup">Follow up Date:</label>
            <input type="text" class="rx-input" id="rxFollowup" autocomplete="off">
        </div>
        <div class="rx-sign">
            <img id="rxSignImage" class="rx-sign-image" alt="Dentist signature">
            <input type="text" class="rx-input rx-sign-input" id="rxDoctor" autocomplete="off">
            <div class="rx-sign-caption">Dentist's Name</div>
            <div class="rx-lic"><label for="rxLicNo">Lic. No.:</label><input type="text" class="rx-input" id="rxLicNo" autocomplete="off"></div>
            <div class="rx-lic"><label for="rxPtrNo">PTR No.:</label><input type="text" class="rx-input" id="rxPtrNo" autocomplete="off"></div>
        </div>
    </div>
</div>
