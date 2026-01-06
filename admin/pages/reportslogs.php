<!-- Grade/Section Table -->
                <div class="table-container">
                    <div class="section-title">
                        <div><i class="fas fa-users"></i> Report per Grade / Section</div>
                        <div class="table-actions">
                            <button class="action-btn print" onclick="printTable('gradeTable', 'Grade Section Report')">
                                <i class="fas fa-print"></i> Print
                            </button>
                         
                        </div>
                    </div>
                    
                    <div class="table-wrapper">
                        <?php if ($grade_result && $grade_result->num_rows > 0): ?>
                            <table id="gradeTable">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Grade / Section</th>
                                        <th>Total Records</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $i = 1;
                                    $grade_total = 0;
                                    $grade_result->data_seek(0);
                                    while ($row = $grade_result->fetch_assoc()) $grade_total += $row['total_records'];
                                    $grade_result->data_seek(0);
                                    while ($row = $grade_result->fetch_assoc()):
                                    ?>
                                        <tr>
                                            <td><?php echo $i++; ?></td>
                                            <td><?php echo htmlspecialchars($row['grade_section']); ?></td>
                                            <td><span class="case-count"><?php echo $row['total_records']; ?></span></td>
                                        </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="no-data">
                                <i class="fas fa-school"></i>
                                <h3>No grade data available</h3>
                                <p>No grade/section data found for the selected month.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>