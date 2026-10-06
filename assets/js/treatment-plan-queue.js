function treatmentPlanQueueBadge(item) {
    return item.treatment_plan_id ? '<span class="treatment-plan-tag">Treatment Plan</span>' : '';
}
